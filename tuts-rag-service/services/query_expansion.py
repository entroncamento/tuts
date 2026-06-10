import json
import re
import uuid

from fastapi import Request

# Removido o sys.path.append (depender da estrutura do projeto/PYTHONPATH é mais robusto)

from config import logger
from services.iaedu import chamar_iaedu
from prompts.query import prompt_decomposicao, prompt_reescrita

_PADROES_RESUMO = re.compile(
    r"(resume|resumo|síntese|sumário|visão geral|overview|"
    r"quais?\s+s(ão|ao)\s+os\s+temas\s+principais|"
    r"o\s+que\s+est[aá]\s+nos\s+(pdfs?|documentos?|ficheiros?)|"
    r"explica\s+tudo\s+sobre|"
    r"não\s+sei\s+nada\s+sobre|"
    r"do\s+que\s+trata\s+esta\s+matéria|"
    r"que\s+matéria\s+sai\s+no\s+teste|"
    r"que\s+conteúdos?\s+tenho\s+de\s+estudar)",
    re.IGNORECASE,
)

_PADROES_REFERENCIA_HISTORICO = re.compile(
    r"\b(isso|isto|aquilo|ele|ela|essa|esse|aquela|aquele|o mesmo|a mesma)\b",
    re.IGNORECASE,
)

_PADROES_PERGUNTA_SIMPLES = re.compile(
    r"\b(o que é|o que faz|qual a diferença|como funciona|porque é que|como é que)\b",
    re.IGNORECASE,
)


def _preparar_historico_seguro(mensagens: list) -> str:
    """
    Trunca e limpa o histórico para evitar exfiltração massiva de dados e 
    esconder potenciais payloads complexos do LLM externo.
    """
    if not mensagens:
        return "[]"
        
    historico_recente = mensagens[-3:]
    historico_seguro = []
    
    for msg in historico_recente:
        role = msg.get("role", "user")
        # Truncamento estrito para limitar a exposição de dados
        content = str(msg.get("content", ""))[:300]
        # Sanitização básica para dificultar fugas de sintaxe no prompt
        content = content.replace("```", "").replace("<", "&lt;").replace(">", "&gt;")
        
        historico_seguro.append({"role": role, "content": content})
        
    return json.dumps(historico_seguro, ensure_ascii=False)


def _proteger_query_contra_injection(texto: str) -> str:
    """
    Envolve a query do aluno em delimitadores estritos e limpa caracteres perigosos,
    para que o LLM a trate como dados e não como instruções de sistema.
    """
    texto_limpo = texto.replace("```", "").replace('"""', '').strip()
    # CORREÇÃO: String na mesma linha. Os \n tratam da formatação interna para o LLM.
    return f"```texto_do_aluno\n{texto_limpo}\n```"


def e_pergunta_de_resumo(texto: str) -> bool:
    texto = texto.strip()
    if len(texto.split()) <= 2:
        return False
    return bool(_PADROES_RESUMO.search(texto))


def query_resumo_para_uc(uc_nome: str) -> str:
    return f"introdução conceitos fundamentais temas principais conteúdos teoria definição {uc_nome}"


def _pergunta_simples(texto: str) -> bool:
    t = texto.strip()
    palavras = t.split()
    if len(palavras) <= 12:
        return True
    return bool(_PADROES_PERGUNTA_SIMPLES.search(t)) and len(palavras) <= 18


def _precisa_reescrita_com_historico(texto: str, mensagens_historico: list) -> bool:
    return bool(mensagens_historico) and bool(_PADROES_REFERENCIA_HISTORICO.search(texto))


def _precisa_decomposicao(texto: str) -> bool:
    palavras = texto.split()
    if len(palavras) < 14:
        return False

    sinais_complexidade = [
        "," in texto,
        ";" in texto,
        " e " in texto.lower(),
        " ou " in texto.lower(),
        " diferença" in texto.lower(),
        " compara" in texto.lower(),
    ]
    return any(sinais_complexidade)


async def expandir_queries(
    texto_final: str,
    tem_imagem: bool,
    modo_resumo: bool,
    uc_nome: str,
    mensagens_historico: list,
    thread_id: str,
    request: Request,
) -> list[str]:
    
    if modo_resumo:
        return [query_resumo_para_uc(uc_nome)]

    if tem_imagem:
        return [texto_final]

    if _pergunta_simples(texto_final):
        return [texto_final]

    temp_thread = f"bg_task_{uuid.uuid4().hex}"

    if _precisa_reescrita_com_historico(texto_final, mensagens_historico):
        try:
            hist_seguro = _preparar_historico_seguro(mensagens_historico)
            texto_protegido = _proteger_query_contra_injection(texto_final)

            query_reescrita = await chamar_iaedu(
                prompt_reescrita(hist_seguro, texto_protegido),
                temp_thread,
                request,
            )
            
            query_limpa = query_reescrita.replace("```texto_do_aluno", "").replace("```", "").strip()
            if query_limpa:
                return [query_limpa]
                
        except Exception as exc:
            # Apenas registamos a natureza do erro. Não expomos dados do utilizador.
            logger.error("[EXPANSÃO] Falha na reescrita com histórico. Erro: %s", type(exc).__name__)

    if _precisa_decomposicao(texto_final):
        try:
            texto_protegido = _proteger_query_contra_injection(texto_final)
            
            decomp_response = await chamar_iaedu(
                prompt_decomposicao(texto_protegido),
                temp_thread,
                request,
            )
            
            subqueries = [
                sq.strip().replace("```texto_do_aluno", "").replace("```", "")
                for sq in decomp_response.split("\n")
                if sq.strip() and len(sq) > 5
            ]
            if len(subqueries) > 1:
                return [texto_final, *subqueries[:2]]
                
        except Exception as exc:
            logger.error("[EXPANSÃO] Falha na decomposição da query. Erro: %s", type(exc).__name__)

    return [texto_final]