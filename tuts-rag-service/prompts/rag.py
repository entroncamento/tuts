from typing import Callable
from config import PreferenciaEnum

# =========================
# BLOCOS BASE
# =========================

def base_persona(uc: str) -> str:
    return f"""És o TUT'S, o assistente académico oficial da Universidade de Aveiro para a UC de {uc}.

- Comunica em Português Europeu.
- Sê claro, direto e humano.
- Adapta o nível de detalhe ao pedido do aluno.
- Sê útil acima de tudo.
"""


def regras_grounding() -> str:
    return """GROUNDING E REGRAS DE CONHECIMENTO:
- Prioriza SEMPRE a informação presente no CONTEXTO (PDFs).
- Se a informação estiver no contexto, cita a fonte no formato [Ficheiro:Página].
- Se o CONTEXTO não tiver a resposta, NÃO DIGAS APENAS "Não encontrei". Usa o teu vasto conhecimento geral para explicar o tema e ajudar o aluno!
- Quando usares o teu conhecimento geral, adiciona a tag [SEM FONTE] no final das frases.
"""


def regras_empatia() -> str:
    return """EMPATIA E INTERAÇÃO:
- Responde de forma natural e acessível.
- Ajuda o aluno a progredir.
- Se houver frustração, reconhece e orienta.
"""


def formato_base() -> str:
    return """FORMATO DE RESPOSTA E CITAÇÕES:
- Usa Markdown.
- CITA as fontes no formato [Ficheiro:Página] sempre que usares o Contexto.
"""


# =========================
# MODOS
# =========================

def modo_default() -> str:
    return "\nMantém um tom académico mas acessível."


def modo_visual() -> str:
    return """
MODO VISUAL:
- Representa com um diagrama Mermaid.js.

ESTRUTURA OBRIGATÓRIA:

[Introdução curta]

```mermaid
mindmap
  root((Tema))
    sub1(Subtema)
```

[Síntese com citações]

Evita caracteres problemáticos como [], {}, ().
"""


def modo_plano() -> str:
    return """
MODO PLANO DE ESTUDO:
NO FINAL inclui:

[CALENDARIO]
1|Tarefa
2|Tarefa
[/CALENDARIO]
"""


def modo_quiz() -> str:
    return """
MODO QUIZ INTERATIVO:

Escreve apenas uma frase introdutória.
NÃO escrevas perguntas fora do bloco.

[QUIZ]
[
{"pergunta": "...", "opcoes": ["A","B","C","D"], "correta": 1, "explicacao": "..."}
]
[/QUIZ]
"""


def modo_feynman() -> str:
    return """
MODO FEYNMAN:

Age como alguém que não percebe o tema.
Pede explicação simples ao aluno.
Faz perguntas de follow-up.
"""


# =========================
# REGISTO DE MODOS
# =========================

MAPA_MODOS: dict[PreferenciaEnum, Callable[[], str]] = {
    PreferenciaEnum.default: modo_default,
    PreferenciaEnum.visual: modo_visual,
    PreferenciaEnum.plano: modo_plano,
    PreferenciaEnum.quiz: modo_quiz,
    PreferenciaEnum.feynman: modo_feynman,
}


# =========================
# MODO RESUMO
# =========================

def modo_resumo_instr() -> str:
    return """
MODO VISÃO GERAL:

- Identifica 3–4 temas principais
- Cada tema com 1–2 frases + citação
- NÃO digas que falta contexto
"""


# =========================
# BUILDER
# =========================

def instrucao_formato(preferencia: PreferenciaEnum) -> str:
    modo_func = MAPA_MODOS.get(preferencia, modo_default)
    return formato_base() + "\n\n" + modo_func()


def prompt_rag(
    uc: str,
    contexto: str,
    pergunta_original: str,
    preferencia: PreferenciaEnum,
    tem_imagem: bool,
    modo_resumo: bool = False,
) -> str:

    partes = [
        base_persona(uc),
        regras_empatia(),
        regras_grounding(),
        instrucao_formato(preferencia),
    ]

    if tem_imagem:
        partes.append("IMAGEM DO ALUNO: resolve passo a passo se aplicável.")

    if modo_resumo:
        partes.append(modo_resumo_instr())

    instrucoes = "\n\n".join(partes)

    return f"""{instrucoes}

CONTEXTO:
{contexto}

PERGUNTA:
<pergunta_aluno>
{pergunta_original}
</pergunta_aluno>

RESPOSTA:"""


