import httpx

with httpx.stream(
    'POST', 'http://localhost:8001/perguntar',
    headers={'x-internal-token': 'TUTS_SUPER_SECRET_123'},
    data={'texto': 'ola', 'uc': 'Marca & Portfolio', 'preferencia': 'textual'},
    timeout=30
) as r:
    for line in r.iter_lines():
        if line:
            print(line)
