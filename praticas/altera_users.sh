#!/bin/bash

# Script para remover a obrigação de mudança de password
# nos utilizadores formando1 a formando20

if [[ $EUID -ne 0 ]]; then
    echo "❌ Este script deve ser executado como root (sudo)."
    exit 1
fi

echo "=== A remover obrigação de mudança de password ==="
echo ""

for i in $(seq 1 20); do
    USERNAME="formando${i}"

    if id "${USERNAME}" &>/dev/null; then
        chage -d -1 "${USERNAME}"
        echo "✅ ${USERNAME} — password já não precisa de ser alterada no próximo login."
    else
        echo "⚠️  Utilizador '${USERNAME}' não existe — ignorado."
    fi
done

echo ""
echo "=== Concluído ==="
