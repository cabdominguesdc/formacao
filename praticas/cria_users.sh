#!/bin/bash

# Script para criar 20 utilizadores Linux: formando1..formando20
# Passwords: FRM2026_1..FRM2026_20
# Home: /home/formandoN

# Deve ser executado como root
if [[ $EUID -ne 0 ]]; then
    echo "❌ Este script deve ser executado como root (sudo)."
    exit 1
fi

echo "=== Criação de utilizadores formando1 a formando20 ==="
echo ""

for i in $(seq 1 20); do
    USERNAME="formando${i}"
    PASSWORD="FRM2026_${i}"
    HOMEDIR="/home/${USERNAME}"

    # Verificar se o utilizador já existe
    if id "${USERNAME}" &>/dev/null; then
        echo "⚠️  Utilizador '${USERNAME}' já existe — ignorado."
        continue
    fi

    # Criar utilizador com home directory
    useradd -m -d "${HOMEDIR}" -s /bin/bash "${USERNAME}"

    if [[ $? -eq 0 ]]; then
        # Definir password
        echo "${USERNAME}:${PASSWORD}" | chpasswd

        # Forçar alteração de password no primeiro login (opcional — remover se não pretendido)
        chage -d 0 "${USERNAME}"

        echo "✅ Utilizador '${USERNAME}' criado | Home: ${HOMEDIR} | Password: ${PASSWORD}"
    else
        echo "❌ Erro ao criar o utilizador '${USERNAME}'."
    fi
done

echo ""
echo "=== Concluído ==="
echo ""
echo "Resumo dos utilizadores criados:"
echo "-----------------------------------"
for i in $(seq 1 20); do
    id "formando${i}" &>/dev/null && echo "  formando${i} (UID: $(id -u formando${i}))"
done

