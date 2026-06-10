# Preliminary Proof-of-Concept Evaluation — TUT'S

## Scope
This report describes a preliminary technical proof-of-concept evaluation of a RAG-based tutoring pipeline. It does not constitute a full pedagogical validation, a usability study, or evidence of learning gains.

## Configuration
- UC: `Tecnologias_Avancadas_para_Client-side`
- Dataset items: 10
- Judge model: `llama-3.1-8b-instant`
- Run ID: `2c821d2bf519`
- Duration: 226s

## Quality-only results
- Valid evaluated responses: 10
- In-scope responses: 9
- Out-of-scope responses: 1
- Faithfulness mean: 4.333
- Relevance mean: 4.222
- Pedagogy mean: 4.111
- Composite score mean: 4.239
- Refusal quality mean: 5
- Items flagged for human review: 9

## Robustness results
- TUT'S OK / dataset: 1.0
- Judge OK / dataset: 1.0
- Sem contexto / TUT'S OK: 0.1
- Mean TUT'S latency: 11.918s
- Mean judge latency: 9.567s
- Suspected truncations: 0
- Citation-detection flags in in-scope items: 1
- Strict must-not violations: 0
- Possible must-not flags requiring human review: 0

## Human review flags
- faltam_criterios_essenciais: 8
- score_perfeito_rever_manualmente: 1
- sem_citacao_detectada: 1

## Recommended wording for paper
A preliminary proof-of-concept evaluation was conducted to assess whether the proposed RAG-based tutoring mechanism could process course-related questions, generate grounded explanations, and refuse out-of-scope requests. The results should be interpreted cautiously, as the evaluation used a small dataset and an automated LLM-based judge; therefore, it supports technical feasibility rather than validated pedagogical effectiveness.

## Limitations
- Small dataset.
- Automated LLM-based judgement must be complemented with human review.
- No measurement of student learning outcomes.
- No longitudinal deployment or controlled comparison group.