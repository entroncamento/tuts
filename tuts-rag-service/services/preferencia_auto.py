import re
from config import PreferenciaEnum

# Limite máximo de caracteres analisados para evitar ReDoS e picos de CPU
MAX_TEXT_LENGTH = 500

# Pré-compilação das expressões regulares para máxima performance no FastAPI.
# Ordem importa: quiz/plano/visual/feynman antes do default
PADROES = {
    PreferenciaEnum.quiz: [
        re.compile(r"\bquiz\b", re.IGNORECASE),
        re.compile(r"\bteste\b", re.IGNORECASE),
        re.compile(r"\bquestion[aá]rio\b", re.IGNORECASE),
        re.compile(r"\bperguntas de escolha m[uú]ltipla\b", re.IGNORECASE),
        re.compile(r"\bm[cç]q\b", re.IGNORECASE),
        re.compile(r"\bavalia[- ]?me\b", re.IGNORECASE),
        re.compile(r"\bp[oõ]e[- ]?me à prova\b", re.IGNORECASE),
        re.compile(r"\bfaz[- ]?me perguntas\b", re.IGNORECASE),
    ],
    PreferenciaEnum.plano: [
        re.compile(r"\bplano de estudo\b", re.IGNORECASE),
        re.compile(r"\bcronograma\b", re.IGNORECASE),
        re.compile(r"\bcalend[aá]rio\b", re.IGNORECASE),
        re.compile(r"\bagenda\b", re.IGNORECASE),
        re.compile(r"\borganiza[- ]?me o estudo\b", re.IGNORECASE),
        re.compile(r"\bcomo estudar\b", re.IGNORECASE),
        re.compile(r"\broteiro de estudo\b", re.IGNORECASE),
        re.compile(r"\bplano\b", re.IGNORECASE),
    ],
    PreferenciaEnum.visual: [
        re.compile(r"\bgr[aá]fico[s]?\b", re.IGNORECASE), # Fundido singular e plural
        re.compile(r"\bdiagrama\b", re.IGNORECASE),
        re.compile(r"\besquema\b", re.IGNORECASE),
        re.compile(r"\bmapa mental\b", re.IGNORECASE),
        re.compile(r"\bfluxograma\b", re.IGNORECASE),
        re.compile(r"\bvisualiza\b", re.IGNORECASE),
        re.compile(r"\bexplica visualmente\b", re.IGNORECASE),
        re.compile(r"\bresumo visual\b", re.IGNORECASE),
        re.compile(r"\bmermaid\b", re.IGNORECASE),
    ],
    PreferenciaEnum.feynman: [
        re.compile(r"\bfeynman\b", re.IGNORECASE),
        re.compile(r"\beu vou explicar\b", re.IGNORECASE),
        re.compile(r"\bdeixa[- ]?me explicar\b", re.IGNORECASE),
        re.compile(r"\bouve a minha explica[cç][aã]o\b", re.IGNORECASE),
        re.compile(r"\bcorrige[- ]?me\b", re.IGNORECASE),
        re.compile(r"\btesta[- ]?me\b", re.IGNORECASE),
        re.compile(r"\bavalia a minha explica[cç][aã]o\b", re.IGNORECASE),
        re.compile(r"\bn[aã]o me d[eê]s logo a resposta\b", re.IGNORECASE),
    ],
}


def detetar_preferencia_automatica(
    texto: str,
    preferencia_manual: PreferenciaEnum,
) -> PreferenciaEnum:
    """
    Se o utilizador tiver escolhido manualmente um modo != default,
    respeitamos essa escolha.
    Se estiver em default, tentamos inferir automaticamente com base 
    nos primeiros N caracteres do texto.
    """
    if preferencia_manual != PreferenciaEnum.default:
        return preferencia_manual

    # Truncar o texto de forma segura logo no início
    t = (texto or "").strip()[:MAX_TEXT_LENGTH]
    
    if not t:
        return PreferenciaEnum.default

    scores = {
        PreferenciaEnum.quiz: 0,
        PreferenciaEnum.plano: 0,
        PreferenciaEnum.visual: 0,
        PreferenciaEnum.feynman: 0,
    }

    for modo, padroes_compilados in PADROES.items():
        for regex in padroes_compilados:
            if regex.search(t):
                scores[modo] += 1

    melhor_modo = max(scores, key=scores.get)
    melhor_score = scores[melhor_modo]

    if melhor_score == 0:
        return PreferenciaEnum.default

    return melhor_modo