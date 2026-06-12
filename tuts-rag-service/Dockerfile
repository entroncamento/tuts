FROM python:3.12-slim

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PIP_NO_CACHE_DIR=1

WORKDIR /app

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        build-essential \
        curl \
        libgl1 \
        libglib2.0-0 \
        libgomp1 \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt ./
RUN python -m pip install --upgrade pip \
    && TORCH_VERSION="$(grep -E '^torch==' requirements.txt | cut -d= -f3)" \
    && TORCHVISION_VERSION="$(grep -E '^torchvision==' requirements.txt | cut -d= -f3)" \
    && python -m pip install --no-deps --index-url https://download.pytorch.org/whl/cpu "torch==${TORCH_VERSION}+cpu" "torchvision==${TORCHVISION_VERSION}+cpu" \
        || python -m pip install --no-deps --index-url https://download.pytorch.org/whl/cpu "torch==${TORCH_VERSION}" "torchvision==${TORCHVISION_VERSION}" \
    && grep -Ev '^(torch|torchvision|triton)==|^(nvidia-|cuda-)' requirements.txt > /tmp/requirements-docker.txt \
    && python -m pip install -r /tmp/requirements-docker.txt

COPY . .

RUN mkdir -p /app/faiss_db /app/pdfs

EXPOSE 8001

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD python -c "import urllib.request; urllib.request.urlopen('http://127.0.0.1:8001/health', timeout=3).read()" || exit 1

CMD ["python", "-m", "uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8001"]
