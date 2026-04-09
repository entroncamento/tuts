import re
import json
import sys
import os
import uuid
from fastapi import Request

sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), '..')))

from config import logger
from services.iaedu import chamar_iaedu
from prompts.query import prompt_reescrita, prompt_decomposicao

# Padrões que indicam pedido de resumo/visão geral da UC.
_PADROES_RESUMO = re.compile(
    r"(qual|quais).{0,20}(mat[eé]ria|conte[uú]do|assunto|t[oó]pico|tema)"
    r"|o que.{0,15}(pdf|documento|ficheiro|est[aá] nos)"
    r"|(resume|resumo|s[íi]ntese|sum[aá]rio|visão geral|overview|explica tudo"
    r"|do que trata|fala sobre o qu[eê]|do que [eé]|do que se trata"
    r"|teste|exame|avalia[çc][ãa]o|ajud[ea]|n[ãa]o sei nada|perdido"
    r"|ol[aá]|bom dia|boa tarde|boa noite|tudo bem)",
    re.IGNORECASE,
)

def e_pergunta_de_resumo(texto: str) -> bool:
    return bool(_PADROES_RESUMO.search(texto))

def query_resumo_para_uc(uc_nome: str) -> str:
    return f"introdução conceitos fundamentais temas principais conteúdos teoria definição {uc_nome}"

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

    temp_thread = f"bg_task_{uuid.uuid4().hex}"

    if mensagens_historico:
        try:
            hist_json = json.dumps(mensagens_historico[-3:], ensure_ascii=False)
            query_reescrita = await chamar_iaedu(prompt_reescrita(hist_json, texto_final), temp_thread, request)
            if query_reescrita.strip():
                return [query_reescrita]
        except Exception as exc:
            logger.warning("Falha na reescrita de query com histórico: %s", exc)

    if not tem_imagem:
        try:
            decomp_response = await chamar_iaedu(prompt_decomposicao(texto_final), temp_thread, request)
            subqueries = [sq.strip() for sq in decomp_response.split("\n") if sq.strip() and len(sq) > 5]
            if len(subqueries) > 1:
                return [texto_final, *subqueries[:3]]
        except Exception as exc:
            logger.warning("Falha na decomposição de query: %s", exc)

    return [texto_final]