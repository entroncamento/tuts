import asyncio
from functools import partial
from fastapi import UploadFile
from core.ml_models import leitor_ocr, executor
from config import logger

async def processar_ocr(imagem: UploadFile, max_img_bytes: int, texto_base: str) -> tuple[str, bool]:
    loop = asyncio.get_running_loop()
    try:
        conteudo_img = await imagem.read()
        if len(conteudo_img) > max_img_bytes: return texto_base, False
        
        funcao_ocr = partial(leitor_ocr.readtext, conteudo_img, detail=0)
        resultados_ocr = await loop.run_in_executor(executor, funcao_ocr)
        texto_extraido = "\n".join(resultados_ocr)
        
        if texto_extraido.strip():
            return texto_base + f"\n\n[TEXTO IMAGEM]:\n{texto_extraido}", True
    except Exception as exc:
        logger.warning("Erro OCR: %s", exc)
    return texto_base, False