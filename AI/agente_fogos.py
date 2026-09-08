#!/usr/bin/env python3
"""
Agente de monitorização e notificação de incêndios ativos (área de Coimbra).

Fluxo:
  1. Vai buscar a lista de incêndios ativos em Portugal (fonte: fogos.pt).
  2. Filtra pela área definida (distrito e, opcionalmente, concelho).
  3. Para incêndios NOVOS (ainda não notificados), pede a um LLM via OpenRouter
     um resumo/análise de risco em português.
  4. Notifica (consola sempre; email opcionalmente, se SMTP estiver configurado).
  5. Guarda estado local para não repetir notificações do mesmo incêndio.

Uso:
    python fire_watch_agent.py            # corre uma vez e termina
    python fire_watch_agent.py --loop     # corre em contínuo (systemd/screen/tmux)
    python fire_watch_agent.py --test     # usa dados de exemplo, não chama a API de incêndios
                                           # (em --test o estado local NÃO é lido nem gravado,
                                           #  por isso podes correr várias vezes seguidas)
    python fire_watch_agent.py --verbose  # mostra detalhe por incêndio + prompt/resposta do LLM

Configuração: variáveis de ambiente (ver .env.example).
"""

import os
import sys
import json
import time
import logging
import smtplib
from email.mime.text import MIMEText
from pathlib import Path
from datetime import datetime, timezone

import requests

# --------------------------------------------------------------------------
# Configuração (via variáveis de ambiente — ver .env.example)
# --------------------------------------------------------------------------

# Fonte de dados de incêndios ativos — API oficial da fogos.pt (requer registo e chave).
# Ver README para instruções de registo. Se precisares de voltar ao endpoint
# não-oficial (sem chave, mas sujeito a limitação de pedidos), define FOGOS_API_URL
# como https://api-dev.fogos.pt/new/fires e limpa FOGOS_API_KEY.
FOGOS_API_URL = os.getenv("FOGOS_API_URL", "https://api.fogos.pt/v2/incidents/active")
FOGOS_API_KEY = os.getenv("FOGOS_API_KEY", "")
FOGOS_API_KEY_HEADER = os.getenv("FOGOS_API_KEY_HEADER", "X-API-Key")
FOGOS_API_KEY_PREFIX = os.getenv("FOGOS_API_KEY_PREFIX", "")  # a API oficial usa a chave "nua", sem "Bearer "
FOGOS_MAX_RETRIES = int(os.getenv("FOGOS_MAX_RETRIES", "3"))

# Área a monitorizar. CONCELHO vazio = todo o distrito.
FILTER_DISTRICT = os.getenv("FIRE_DISTRICT", "Coimbra")
FILTER_CONCELHO = os.getenv("FIRE_CONCELHO", "")  # ex.: "Coimbra" para só o concelho

# OpenRouter (LLM)
OPENROUTER_API_KEY = os.getenv("OPENROUTER_API_KEY")
OPENROUTER_MODEL = os.getenv("OPENROUTER_MODEL", "openai/gpt-4o-mini")
OPENROUTER_URL = "https://openrouter.ai/api/v1/chat/completions"

# Notificação por email (opcional — se algum destes faltar, o email é ignorado)
SMTP_HOST = os.getenv("SMTP_HOST")
SMTP_PORT = int(os.getenv("SMTP_PORT", "587"))
SMTP_USER = os.getenv("SMTP_USER")
SMTP_PASS = os.getenv("SMTP_PASS")
NOTIFY_EMAIL_TO = os.getenv("NOTIFY_EMAIL_TO")
NOTIFY_EMAIL_FROM = os.getenv("NOTIFY_EMAIL_FROM", SMTP_USER)

# Intervalo de verificação em modo --loop (segundos)
POLL_INTERVAL_SECONDS = int(os.getenv("POLL_INTERVAL_SECONDS", "300"))

BASE_DIR = Path(__file__).resolve().parent
STATE_FILE = BASE_DIR / "fires_state.json"
LOG_FILE = BASE_DIR / "fires_agent.log"

# Evita "incC*ndios" em terminais/SSH cujo locale não seja UTF-8 (o problema é do
# terminal, mas isto garante que o Python emite sempre bytes UTF-8 corretamente).
try:
    sys.stdout.reconfigure(encoding="utf-8")
except AttributeError:
    pass  # Python < 3.7, improvável aqui

VERBOSE = "--verbose" in sys.argv or "-v" in sys.argv

logging.basicConfig(
    level=logging.DEBUG if VERBOSE else logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    handlers=[
        logging.FileHandler(LOG_FILE, encoding="utf-8"),
        logging.StreamHandler(sys.stdout),
    ],
)
log = logging.getLogger("fire-watch-agent")


# --------------------------------------------------------------------------
# Aquisição de dados
# --------------------------------------------------------------------------

def fetch_active_fires() -> list:
    """Vai buscar a lista completa de incêndios (ativos e recentes) à fonte configurada.

    Em caso de 429 (Too Many Requests), respeita o cabeçalho Retry-After do servidor
    (se vier) e tenta novamente até FOGOS_MAX_RETRIES vezes, com backoff progressivo.
    """
    headers = {}
    if FOGOS_API_KEY:
        headers[FOGOS_API_KEY_HEADER] = f"{FOGOS_API_KEY_PREFIX}{FOGOS_API_KEY}"

    last_exc = None
    for attempt in range(1, FOGOS_MAX_RETRIES + 1):
        resp = requests.get(FOGOS_API_URL, headers=headers, timeout=15)

        if resp.status_code == 429:
            retry_after = resp.headers.get("Retry-After")
            wait = int(retry_after) if retry_after and retry_after.isdigit() else attempt * 10
            log.warning(
                "429 Too Many Requests (tentativa %d/%d). A aguardar %ds antes de repetir. "
                "Corpo da resposta: %s",
                attempt, FOGOS_MAX_RETRIES, wait, resp.text[:300],
            )
            last_exc = requests.exceptions.HTTPError(
                f"429 Client Error: Too Many Requests for url: {FOGOS_API_URL}", response=resp
            )
            if attempt < FOGOS_MAX_RETRIES:
                time.sleep(wait)
                continue
            raise last_exc

        resp.raise_for_status()
        payload = resp.json()

        if isinstance(payload, dict) and "data" in payload:
            return payload.get("data", [])
        if isinstance(payload, list):
            return payload
        raise RuntimeError("Formato de resposta da API de incêndios não reconhecido.")

    raise last_exc


def _normalize(text) -> str:
    return (text or "").strip().lower()


def matches_area(fire: dict) -> bool:
    if FILTER_DISTRICT and _normalize(fire.get("district")) != _normalize(FILTER_DISTRICT):
        return False
    if FILTER_CONCELHO and _normalize(fire.get("concelho")) != _normalize(FILTER_CONCELHO):
        return False
    return True


# --------------------------------------------------------------------------
# Estado local (para não notificar duas vezes o mesmo incêndio)
# --------------------------------------------------------------------------

def load_state() -> dict:
    if STATE_FILE.exists():
        try:
            return json.loads(STATE_FILE.read_text(encoding="utf-8"))
        except json.JSONDecodeError:
            log.warning("Ficheiro de estado corrompido — a recomeçar do zero.")
    return {"known_ids": []}


def save_state(state: dict) -> None:
    STATE_FILE.write_text(json.dumps(state, ensure_ascii=False, indent=2), encoding="utf-8")


# --------------------------------------------------------------------------
# Análise via OpenRouter
# --------------------------------------------------------------------------

SYSTEM_PROMPT = (
    "És um assistente de apoio à proteção civil. Analisas dados de incêndios ativos "
    "em Portugal e produzes um resumo curto, claro e acionável em português de Portugal "
    "(sem expressões do português do Brasil). Para cada incêndio, indica localização, "
    "gravidade aparente com base nos meios envolvidos (nº de operacionais, veículos, meios "
    "aéreos) e o estado atual. Termina com uma nota geral de risco para a área e, se aplicável, "
    "recomendações básicas de precaução. Não inventes dados que não estejam nos factos fornecidos."
)


def call_openrouter(prompt: str) -> str:
    if not OPENROUTER_API_KEY:
        raise RuntimeError("OPENROUTER_API_KEY não definida.")

    headers = {
        "Authorization": f"Bearer {OPENROUTER_API_KEY}",
        "Content-Type": "application/json",
    }
    body = {
        "model": OPENROUTER_MODEL,
        "messages": [
            {"role": "system", "content": SYSTEM_PROMPT},
            {"role": "user", "content": prompt},
        ],
        "temperature": 0.3,
    }
    resp = requests.post(OPENROUTER_URL, headers=headers, json=body, timeout=30)
    resp.raise_for_status()
    data = resp.json()
    return data["choices"][0]["message"]["content"].strip()


def build_prompt(fires: list) -> str:
    lines = []
    for f in fires:
        lines.append(
            f"- ID {f.get('id')}: {f.get('detailLocation') or f.get('location')} "
            f"({f.get('concelho')}, {f.get('district')}). "
            f"Estado: {f.get('status')}. Natureza: {f.get('natureza')}. "
            f"Meios: {f.get('man', 0)} operacionais, {f.get('terrain', 0)} veículos terrestres, "
            f"{f.get('aerial', 0)} meios aéreos. "
            f"Atualizado em: {f.get('date')} {f.get('hour')}."
        )
    return (
        "Segue a lista de incêndios ativos NOVOS detetados na área monitorizada. "
        "Produz o resumo/análise conforme instruções:\n\n" + "\n".join(lines)
    )


def fallback_summary(fires: list) -> str:
    """Usado se a chamada ao OpenRouter falhar — garante que a notificação sai na mesma."""
    lines = [
        f"- {f.get('detailLocation') or f.get('location')} ({f.get('district')}/{f.get('concelho')}) "
        f"— estado: {f.get('status')}"
        for f in fires
    ]
    return "Análise automática indisponível. Resumo básico dos incêndios novos:\n" + "\n".join(lines)


# --------------------------------------------------------------------------
# Notificação
# --------------------------------------------------------------------------

def notify_console(subject: str, body: str) -> None:
    print("\n" + "=" * 70)
    print(subject)
    print("=" * 70)
    print(body)
    print("=" * 70 + "\n")


def notify_email(subject: str, body: str) -> None:
    if not (SMTP_HOST and SMTP_USER and SMTP_PASS and NOTIFY_EMAIL_TO):
        log.info("SMTP não configurado — envio de email ignorado.")
        return
    msg = MIMEText(body, "plain", "utf-8")
    msg["Subject"] = subject
    msg["From"] = NOTIFY_EMAIL_FROM
    msg["To"] = NOTIFY_EMAIL_TO
    with smtplib.SMTP(SMTP_HOST, SMTP_PORT, timeout=15) as server:
        server.starttls()
        server.login(SMTP_USER, SMTP_PASS)
        server.send_message(msg)
    log.info("Email enviado para %s", NOTIFY_EMAIL_TO)


def notify(subject: str, body: str) -> None:
    notify_console(subject, body)
    try:
        notify_email(subject, body)
    except Exception:
        log.exception("Falha ao enviar email de notificação.")
    # Ponto de extensão: adicionar aqui notify_telegram(), notify_webhook(), etc.


# --------------------------------------------------------------------------
# Execução
# --------------------------------------------------------------------------

SAMPLE_FIRES = [
    {
        "id": "TESTE-0001",
        "location": "Coimbra, Coimbra, Santo António dos Olivais",
        "detailLocation": "Mata do Choupal (zona ribeirinha)",
        "district": "Coimbra",
        "concelho": "Coimbra",
        "status": "Em resolução",
        "natureza": "Mato",
        "man": 12,
        "terrain": 4,
        "aerial": 1,
        "date": datetime.now().strftime("%d-%m-%Y"),
        "hour": datetime.now().strftime("%H:%M"),
        "active": True,
    }
]


def run_once(use_sample: bool = False) -> None:
    # Em modo de teste, o estado persistente é ignorado por completo (não é lido
    # nem gravado), para poderes correr `--test` várias vezes seguidas e veres
    # sempre o mesmo resultado.
    if use_sample:
        known_ids = set()
    else:
        state = load_state()
        known_ids = set(state.get("known_ids", []))
        log.debug("IDs já conhecidos (notificados anteriormente): %s", sorted(known_ids) or "nenhum")

    if use_sample:
        all_fires = SAMPLE_FIRES
    else:
        try:
            all_fires = fetch_active_fires()
        except Exception:
            log.exception("Falha ao obter dados da API de incêndios.")
            return

    log.info("Fonte devolveu %d incêndio(s) no total (todo o país, ativos + recentes).", len(all_fires))

    relevant = [f for f in all_fires if f.get("active", True) and matches_area(f)]
    new_fires = [f for f in relevant if f.get("id") not in known_ids]

    area_label = FILTER_CONCELHO or FILTER_DISTRICT
    if not relevant:
        log.info("Sem incêndios ativos na área monitorizada (%s).", area_label)
    else:
        log.info(
            "%d incêndio(s) ativo(s) na área monitorizada (%s), %d novo(s).",
            len(relevant), area_label, len(new_fires),
        )
        for f in relevant:
            estado_novo = "NOVO" if f.get("id") not in known_ids else "já notificado"
            log.info(
                "  - [%s] %s | %s, %s | estado=%s | meios: %s op./%s veíc./%s aéreos",
                estado_novo, f.get("id"),
                f.get("detailLocation") or f.get("location"), f.get("concelho"),
                f.get("status"), f.get("man", 0), f.get("terrain", 0), f.get("aerial", 0),
            )

    if new_fires:
        prompt = build_prompt(new_fires)
        log.debug("Prompt enviado ao OpenRouter:\n%s", prompt)
        try:
            analysis = call_openrouter(prompt)
            log.debug("Resposta bruta do OpenRouter:\n%s", analysis)
        except Exception:
            log.exception("Falha ao obter análise do OpenRouter — a usar resumo básico.")
            analysis = fallback_summary(new_fires)

        subject = f"[Incêndios] {len(new_fires)} novo(s) incêndio(s) ativo(s) — {area_label}"
        notify(subject, analysis)
    else:
        log.info("Nenhum incêndio novo — não é feita chamada ao OpenRouter nem enviada notificação.")

    if use_sample:
        log.info("Modo de teste: estado NÃO foi gravado (fires_state.json não foi alterado).")
        return

    # Mantém em memória apenas os IDs ainda ativos, para permitir nova notificação
    # se, no futuro, um incêndio com o mesmo ID reaparecer como ativo.
    active_ids_now = {f.get("id") for f in all_fires if f.get("active", True)}
    known_ids = (known_ids & active_ids_now) | {f.get("id") for f in relevant}
    save_state({"known_ids": list(known_ids), "last_run": datetime.now(timezone.utc).isoformat()})


def main() -> None:
    args = sys.argv[1:]
    use_sample = "--test" in args
    loop = "--loop" in args

    if use_sample:
        log.info("Modo de teste: a usar dados de exemplo (sem chamar a API de incêndios).")

    if not loop:
        run_once(use_sample=use_sample)
        return

    log.info("Agente em execução contínua. Intervalo: %ds", POLL_INTERVAL_SECONDS)
    while True:
        run_once(use_sample=use_sample)
        time.sleep(POLL_INTERVAL_SECONDS)


if __name__ == "__main__":
    main()
