def prompt_decomposicao(pergunta: str) -> str:
    return f"""Decompõe a seguinte pergunta complexa académica em 2 a 4 sub-perguntas precisas, focadas num único conceito cada uma, para melhorar a precisão de pesquisa num motor de busca documental.
Se a pergunta já for simples e de conceito único, devolve a mesma pergunta original.
Devolve APENAS as sub-perguntas, uma por linha, sem enumeração, pontos de interrogação ou introduções.

PERGUNTA ORIGINAL:
{pergunta}

SUB-PERGUNTAS:"""

def prompt_reescrita(historico_json: str, pergunta: str) -> str:
    return f"""És um especialista em recuperação de informação académica.
A tua única tarefa é reescrever a última pergunta do aluno numa query de pesquisa autónoma e rica em palavras-chave.
Resolve referências (ele, isso). Expande siglas.
Devolve APENAS a query reformulada.

HISTÓRICO DA CONVERSA:
{historico_json}

ÚLTIMA PERGUNTA:
{pergunta}

QUERY REFORMULADA:"""