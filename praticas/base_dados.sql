-- ============================================================
--  BASE DE DADOS: Formação em Informática
--  Gerado para uso didático/fictício
-- ============================================================

CREATE DATABASE IF NOT EXISTS formacao_informatica
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE formacao_informatica;

-- ============================================================
-- TABELA: disciplinas
-- ============================================================
CREATE TABLE disciplinas (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  codigo     VARCHAR(10)  NOT NULL UNIQUE,
  nome       VARCHAR(100) NOT NULL,
  carga_horaria INT        NOT NULL COMMENT 'Horas totais da disciplina',
  descricao  TEXT,
  criado_em  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- TABELA: formandos
-- ============================================================
CREATE TABLE formandos (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  codigo     VARCHAR(20)  NOT NULL UNIQUE,
  nome       VARCHAR(100) NOT NULL,
  email      VARCHAR(150) NOT NULL UNIQUE,
  data_nascimento DATE,
  telefone   VARCHAR(20),
  criado_em  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- TABELA: formadores (professores)
-- ============================================================
CREATE TABLE formadores (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  nome       VARCHAR(100) NOT NULL,
  email      VARCHAR(150) NOT NULL UNIQUE,
  especialidade VARCHAR(100),
  criado_em  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- TABELA: turmas
-- ============================================================
CREATE TABLE turmas (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  nome         VARCHAR(50) NOT NULL,
  ano_letivo   YEAR        NOT NULL,
  data_inicio  DATE,
  data_fim     DATE,
  criado_em    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- TABELA: inscricoes (formando <-> turma)
-- ============================================================
CREATE TABLE inscricoes (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  formando_id  INT NOT NULL,
  turma_id     INT NOT NULL,
  data_inscricao DATE NOT NULL,
  estado       ENUM('ativo','concluido','desistiu') DEFAULT 'ativo',
  FOREIGN KEY (formando_id) REFERENCES formandos(id),
  FOREIGN KEY (turma_id)    REFERENCES turmas(id),
  UNIQUE KEY uq_inscricao (formando_id, turma_id)
);

-- ============================================================
-- TABELA: aulas
-- ============================================================
CREATE TABLE aulas (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  disciplina_id  INT  NOT NULL,
  turma_id       INT  NOT NULL,
  formador_id    INT  NOT NULL,
  numero_aula    INT  NOT NULL,
  data_aula      DATE NOT NULL,
  hora_inicio    TIME NOT NULL,
  hora_fim       TIME NOT NULL,
  sala           VARCHAR(30),
  FOREIGN KEY (disciplina_id) REFERENCES disciplinas(id),
  FOREIGN KEY (turma_id)      REFERENCES turmas(id),
  FOREIGN KEY (formador_id)   REFERENCES formadores(id)
);

-- ============================================================
-- TABELA: sumarios
-- ============================================================
CREATE TABLE sumarios (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  aula_id     INT  NOT NULL UNIQUE,
  conteudo    TEXT NOT NULL,
  recursos    TEXT COMMENT 'Links/ficheiros utilizados na aula',
  criado_em   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (aula_id) REFERENCES aulas(id)
);

-- ============================================================
-- TABELA: presenças
-- ============================================================
CREATE TABLE presencas (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  aula_id      INT  NOT NULL,
  formando_id  INT  NOT NULL,
  presente     TINYINT(1) DEFAULT 1,
  justificacao VARCHAR(255),
  FOREIGN KEY (aula_id)     REFERENCES aulas(id),
  FOREIGN KEY (formando_id) REFERENCES formandos(id),
  UNIQUE KEY uq_presenca (aula_id, formando_id)
);

-- ============================================================
-- TABELA: testes
-- ============================================================
CREATE TABLE testes (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  disciplina_id  INT          NOT NULL,
  turma_id       INT          NOT NULL,
  formador_id    INT          NOT NULL,
  titulo         VARCHAR(150) NOT NULL,
  tipo           ENUM('ficha','teste_escrito','projeto','oral','pratico') NOT NULL,
  data_realizacao DATE,
  duracao_min    INT  COMMENT 'Duração em minutos',
  cotacao_total  DECIMAL(5,2) NOT NULL DEFAULT 20.00,
  criado_em      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (disciplina_id) REFERENCES disciplinas(id),
  FOREIGN KEY (turma_id)      REFERENCES turmas(id),
  FOREIGN KEY (formador_id)   REFERENCES formadores(id)
);

-- ============================================================
-- TABELA: enunciados_testes
-- ============================================================
CREATE TABLE enunciados_testes (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  teste_id    INT  NOT NULL UNIQUE,
  introducao  TEXT,
  instrucoes  TEXT NOT NULL,
  criado_em   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (teste_id) REFERENCES testes(id)
);

-- ============================================================
-- TABELA: perguntas_teste
-- ============================================================
CREATE TABLE perguntas_teste (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  enunciado_id   INT          NOT NULL,
  numero         INT          NOT NULL,
  texto_pergunta TEXT         NOT NULL,
  tipo           ENUM('escolha_multipla','verdadeiro_falso','desenvolvimento','pratica') NOT NULL,
  cotacao        DECIMAL(4,2) NOT NULL,
  FOREIGN KEY (enunciado_id) REFERENCES enunciados_testes(id)
);

-- ============================================================
-- TABELA: classificacoes
-- ============================================================
CREATE TABLE classificacoes (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  teste_id      INT            NOT NULL,
  formando_id   INT            NOT NULL,
  nota          DECIMAL(5,2)   NOT NULL,
  observacoes   VARCHAR(255),
  data_avaliacao DATE,
  FOREIGN KEY (teste_id)     REFERENCES testes(id),
  FOREIGN KEY (formando_id)  REFERENCES formandos(id),
  UNIQUE KEY uq_classificacao (teste_id, formando_id)
);

-- ============================================================
-- TABELA: notas_finais
-- ============================================================
CREATE TABLE notas_finais (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  formando_id    INT            NOT NULL,
  disciplina_id  INT            NOT NULL,
  turma_id       INT            NOT NULL,
  nota_final     DECIMAL(5,2)   NOT NULL,
  aprovado       TINYINT(1)     GENERATED ALWAYS AS (nota_final >= 9.5) STORED,
  criado_em      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (formando_id)   REFERENCES formandos(id),
  FOREIGN KEY (disciplina_id) REFERENCES disciplinas(id),
  FOREIGN KEY (turma_id)      REFERENCES turmas(id),
  UNIQUE KEY uq_nota_final (formando_id, disciplina_id, turma_id)
);


-- ============================================================
-- ============================================================
--  DADOS FICTÍCIOS
-- ============================================================
-- ============================================================

-- ============================================================
-- Disciplinas
-- ============================================================
INSERT INTO disciplinas (codigo, nome, carga_horaria, descricao) VALUES
('PROG01', 'Fundamentos de Programação',        50, 'Introdução à lógica de programação, algoritmos e pseudocódigo.'),
('PROG02', 'Programação em Python',              60, 'Linguagem Python: sintaxe, estruturas de dados, OOP e módulos.'),
('WEB01',  'Desenvolvimento Web Frontend',       50, 'HTML5, CSS3, JavaScript e frameworks como Bootstrap.'),
('WEB02',  'Desenvolvimento Web Backend',        60, 'PHP/Node.js, APIs REST, bases de dados relacionais.'),
('DB01',   'Bases de Dados Relacionais',          40, 'Modelação de dados, SQL, normalização e MySQL.'),
('NET01',  'Redes de Computadores',               40, 'Fundamentos de redes, TCP/IP, DNS, DHCP e segurança.'),
('SO01',   'Sistemas Operativos',                 35, 'Linux e Windows: administração, scripting e virtualização.'),
('SEC01',  'Cibersegurança Essencial',            45, 'Princípios de segurança, ameaças, criptografia e boas práticas.'),
('GIT01',  'Controlo de Versões com Git',         20, 'Git, GitHub/GitLab, branches, pull requests e CI/CD básico.'),
('CLOUD1', 'Cloud Computing e DevOps',            40, 'AWS/Azure basics, Docker, Kubernetes e pipelines de deployment.');

-- ============================================================
-- Formadores
-- ============================================================
INSERT INTO formadores (nome, email, especialidade) VALUES
('Ana Ferreira',    'ana.ferreira@formacao.pt',    'Programação e Algoritmos'),
('Bruno Cardoso',   'bruno.cardoso@formacao.pt',   'Desenvolvimento Web'),
('Catarina Nunes',  'catarina.nunes@formacao.pt',  'Bases de Dados e SQL'),
('David Oliveira',  'david.oliveira@formacao.pt',  'Redes e Infraestrutura'),
('Elisa Rodrigues', 'elisa.rodrigues@formacao.pt', 'Segurança Informática');

-- ============================================================
-- Turma
-- ============================================================
INSERT INTO turmas (nome, ano_letivo, data_inicio, data_fim) VALUES
('TIC-2024-A', 2024, '2024-01-15', '2024-12-20');

-- ============================================================
-- Formandos (formando1 a formando20)
-- ============================================================
INSERT INTO formandos (codigo, nome, email, data_nascimento, telefone) VALUES
('formando1',  'Alexandre Sousa',     'formando1@turma.pt',  '2001-03-12', '912000001'),
('formando2',  'Beatriz Martins',     'formando2@turma.pt',  '2000-07-25', '912000002'),
('formando3',  'Carlos Lima',         'formando3@turma.pt',  '2001-11-08', '912000003'),
('formando4',  'Diana Costa',         'formando4@turma.pt',  '2002-02-14', '912000004'),
('formando5',  'Eduardo Pires',       'formando5@turma.pt',  '2000-09-30', '912000005'),
('formando6',  'Filipa Gomes',        'formando6@turma.pt',  '2001-05-17', '912000006'),
('formando7',  'Gonçalo Teixeira',    'formando7@turma.pt',  '2003-01-22', '912000007'),
('formando8',  'Helena Barbosa',      'formando8@turma.pt',  '2000-12-05', '912000008'),
('formando9',  'Ivo Mendes',          'formando9@turma.pt',  '2002-08-19', '912000009'),
('formando10', 'Joana Ribeiro',       'formando10@turma.pt', '2001-04-03', '912000010'),
('formando11', 'Kevin Alves',         'formando11@turma.pt', '2000-06-28', '912000011'),
('formando12', 'Leonor Fernandes',    'formando12@turma.pt', '2002-10-11', '912000012'),
('formando13', 'Miguel Santos',       'formando13@turma.pt', '2001-01-16', '912000013'),
('formando14', 'Nádia Correia',       'formando14@turma.pt', '2003-03-29', '912000014'),
('formando15', 'Orlando Faria',       'formando15@turma.pt', '2000-11-07', '912000015'),
('formando16', 'Paula Moreira',       'formando16@turma.pt', '2002-07-20', '912000016'),
('formando17', 'Rodrigo Vieira',      'formando17@turma.pt', '2001-09-02', '912000017'),
('formando18', 'Sara Lopes',          'formando18@turma.pt', '2000-05-15', '912000018'),
('formando19', 'Tomás Pereira',       'formando19@turma.pt', '2003-02-08', '912000019'),
('formando20', 'Ursula Monteiro',     'formando20@turma.pt', '2001-08-24', '912000020');

-- ============================================================
-- Inscrições (todos na turma 1)
-- ============================================================
INSERT INTO inscricoes (formando_id, turma_id, data_inscricao, estado)
SELECT id, 1, '2024-01-10', 'ativo' FROM formandos;

-- ============================================================
-- Aulas (exemplos para 4 disciplinas)
-- ============================================================
INSERT INTO aulas (disciplina_id, turma_id, formador_id, numero_aula, data_aula, hora_inicio, hora_fim, sala) VALUES
-- PROG01 - Fundamentos de Programação (formador 1)
(1, 1, 1, 1, '2024-02-05', '09:00', '12:00', 'Sala 1'),
(1, 1, 1, 2, '2024-02-12', '09:00', '12:00', 'Sala 1'),
(1, 1, 1, 3, '2024-02-19', '09:00', '12:00', 'Sala 1'),
-- PROG02 - Python (formador 1)
(2, 1, 1, 1, '2024-03-04', '09:00', '12:00', 'Lab A'),
(2, 1, 1, 2, '2024-03-11', '09:00', '12:00', 'Lab A'),
(2, 1, 1, 3, '2024-03-18', '09:00', '12:00', 'Lab A'),
-- WEB01 - Frontend (formador 2)
(3, 1, 2, 1, '2024-04-08', '14:00', '17:00', 'Lab B'),
(3, 1, 2, 2, '2024-04-15', '14:00', '17:00', 'Lab B'),
(3, 1, 2, 3, '2024-04-22', '14:00', '17:00', 'Lab B'),
-- DB01 - Bases de Dados (formador 3)
(5, 1, 3, 1, '2024-05-06', '09:00', '12:00', 'Sala 2'),
(5, 1, 3, 2, '2024-05-13', '09:00', '12:00', 'Sala 2'),
(5, 1, 3, 3, '2024-05-20', '09:00', '12:00', 'Sala 2');

-- ============================================================
-- Sumários das aulas
-- ============================================================
INSERT INTO sumarios (aula_id, conteudo, recursos) VALUES
(1,  'Introdução ao curso e à lógica de programação. Conceitos de algoritmo, sequência, seleção e repetição. Exercícios de fluxogramas em papel.',
     'Slides: intro_algoritmos.pdf; Ferramenta: draw.io'),
(2,  'Pseudocódigo: variáveis, tipos de dados e operadores. Resolução de problemas simples: cálculo de média, conversão de temperaturas e verificação de paridade.',
     'Slides: pseudocodigo.pdf; Ferramenta: PseudoStudio'),
(3,  'Estruturas de controlo: if/else e switch. Estruturas de repetição: while e for. Exercícios práticos com fluxogramas e pseudocódigo.',
     'Slides: controlo.pdf; Fichas: exercicios_controlo.pdf'),
(4,  'Introdução ao Python: instalação do ambiente, IDLE e VS Code. Variáveis, tipos primitivos (int, float, str, bool) e operações básicas. Primeiro programa: Hello World e calculadora simples.',
     'Slides: python_intro.pdf; Link: docs.python.org'),
(5,  'Listas, tuplos e dicionários em Python. Iteração com for/while. Compreensão de listas. Exercício: análise estatística de uma lista de notas.',
     'Slides: python_estruturas.pdf; Notebook: aula2_python.ipynb'),
(6,  'Funções em Python: definição, parâmetros, retorno e âmbito. Módulos standard: math, random, datetime. Mini-projeto: gerador de passwords aleatórias.',
     'Slides: python_funcoes.pdf; Repo: github.com/turma/python-exemplos'),
(7,  'Fundamentos de HTML5: estrutura do documento, tags semânticas (header, nav, main, footer, article, section). Formulários HTML e tipos de input.',
     'Slides: html5_semantica.pdf; Ferramenta: VS Code + Live Server'),
(8,  'CSS3: seletores, box model, flexbox e grid layout. Responsividade com media queries. Exercício: construção de uma página de perfil responsiva.',
     'Slides: css3_layout.pdf; Referência: css-tricks.com/snippets/css/a-guide-to-flexbox'),
(9,  'JavaScript essencial: variáveis (let/const), funções, DOM manipulation e eventos. AJAX com fetch API. Exercício: formulário de contacto com validação client-side.',
     'Slides: js_essencial.pdf; Repo: github.com/turma/js-exercicios'),
(10, 'Modelação de dados: entidades, atributos e relações. Diagrama ER. Normalização: 1FN, 2FN e 3FN. Exercício: modelação de um sistema de biblioteca.',
     'Slides: er_normalizacao.pdf; Ferramenta: MySQL Workbench'),
(11, 'SQL: DDL (CREATE, ALTER, DROP) e DML (INSERT, UPDATE, DELETE, SELECT). Cláusulas WHERE, ORDER BY, GROUP BY e HAVING. Exercícios práticos no MySQL.',
     'Slides: sql_ddl_dml.pdf; Script: exercicios_sql.sql'),
(12, 'SQL avançado: JOINs (INNER, LEFT, RIGHT), subqueries e views. Transações e integridade referencial. Exercício: consultas complexas sobre a base de dados da biblioteca.',
     'Slides: sql_avancado.pdf; Script: joins_exercicios.sql');

-- ============================================================
-- Testes
-- ============================================================
INSERT INTO testes (disciplina_id, turma_id, formador_id, titulo, tipo, data_realizacao, duracao_min, cotacao_total) VALUES
(1, 1, 1, 'Ficha Formativa 1 – Algoritmos e Pseudocódigo',         'ficha',          '2024-02-26', 60,  20),
(1, 1, 1, 'Teste Escrito – Fundamentos de Programação',            'teste_escrito',  '2024-03-04', 90,  20),
(2, 1, 1, 'Ficha Prática – Python Estruturas de Dados',            'pratico',        '2024-03-25', 90,  20),
(2, 1, 1, 'Projeto Final Python – Aplicação de Gestão',            'projeto',        '2024-04-15', NULL,20),
(3, 1, 2, 'Ficha Formativa – HTML5 e CSS3',                        'ficha',          '2024-04-29', 60,  20),
(3, 1, 2, 'Projeto Frontend – Portefólio Web Responsivo',          'projeto',        '2024-05-27', NULL,20),
(5, 1, 3, 'Ficha Formativa – Modelação e Normalização',            'ficha',          '2024-05-27', 60,  20),
(5, 1, 3, 'Teste Escrito – Bases de Dados e SQL',                  'teste_escrito',  '2024-06-03', 90,  20);

-- ============================================================
-- Enunciados dos testes
-- ============================================================
INSERT INTO enunciados_testes (teste_id, introducao, instrucoes) VALUES
(1,
 'Esta ficha tem como objetivo avaliar a compreensão dos conceitos fundamentais de algoritmia.',
 'Responde a todas as questões. Não é permitido o uso de calculadora. Duração: 60 minutos.'),
(2,
 'Teste escrito relativo à Unidade 1 – Fundamentos de Programação. Cotação total: 20 valores.',
 'Lê atentamente cada questão antes de responder. Justifica as tuas respostas quando pedido. Duração: 90 minutos.'),
(3,
 'Ficha prática a realizar no computador. Abre o ficheiro notas.py disponibilizado pelo formador.',
 'Implementa as funções pedidas em Python. O código deve executar sem erros. Duração: 90 minutos.'),
(4,
 'Projeto final da disciplina de Python. A aplicação deve gerir um inventário de equipamentos informáticos.',
 'Entrega via GitHub até à data indicada. O projeto deve incluir README, pelo menos 5 funções, persistência em ficheiro JSON e tratamento de erros.'),
(5,
 'Ficha formativa sobre os conteúdos das primeiras duas aulas de Desenvolvimento Web Frontend.',
 'Preenche os espaços em branco e resolve os exercícios de código. Duração: 60 minutos.'),
(6,
 'Projeto final de Frontend. Desenvolve um portefólio pessoal responsivo com pelo menos 4 secções.',
 'Entrega por email com link para repositório público. Deve funcionar corretamente em mobile e desktop. Data limite: 27 de maio de 2024.'),
(7,
 'Ficha formativa sobre modelação de dados e normalização.',
 'Responde às questões teóricas e constrói os diagramas pedidos. Duração: 60 minutos.'),
(8,
 'Teste escrito sobre SQL e Bases de Dados Relacionais. Cotação total: 20 valores.',
 'Escreve as queries SQL pedidas. A sintaxe deve ser compatível com MySQL 8.x. Duração: 90 minutos.');

-- ============================================================
-- Perguntas dos enunciados
-- ============================================================
INSERT INTO perguntas_teste (enunciado_id, numero, texto_pergunta, tipo, cotacao) VALUES
-- Ficha 1 - Algoritmos
(1, 1, 'Define o conceito de algoritmo e apresenta três características que um bom algoritmo deve ter.', 'desenvolvimento', 4.00),
(1, 2, 'Constrói um fluxograma que leia dois números inteiros e exiba o maior deles.', 'desenvolvimento', 6.00),
(1, 3, 'Escreve em pseudocódigo um algoritmo que calcule a média de 5 notas e indique se o aluno foi aprovado (média >= 9.5).', 'desenvolvimento', 6.00),
(1, 4, 'Identifica a estrutura de controlo presente em cada um dos seguintes fragmentos de pseudocódigo (A, B e C).', 'escolha_multipla', 4.00),
-- Teste 2 - Fundamentos
(2, 1, 'Explica a diferença entre uma estrutura de seleção simples e uma estrutura de seleção múltipla. Dá um exemplo de cada.', 'desenvolvimento', 4.00),
(2, 2, 'Escreve em pseudocódigo um algoritmo que leia 10 números e apresente: o maior, o menor e a soma total.', 'desenvolvimento', 6.00),
(2, 3, 'Das seguintes afirmações sobre variáveis, indica quais são Verdadeiras (V) ou Falsas (F): (a) Uma variável pode mudar de valor durante a execução; (b) O nome de uma variável pode começar por um número; (c) Uma variável do tipo inteiro pode armazenar decimais.', 'verdadeiro_falso', 3.00),
(2, 4, 'Analisa o seguinte algoritmo e indica qual o valor final das variáveis x e y após a execução: x <- 5; y <- 3; x <- x + y; y <- x - y; x <- x - y.', 'desenvolvimento', 4.00),
(2, 5, 'Identifica e corrige o erro no seguinte pseudocódigo (troca de dois valores sem variável auxiliar).', 'desenvolvimento', 3.00),
-- Ficha Prática Python
(3, 1, 'Implementa a função `calcular_media(notas: list) -> float` que recebe uma lista de notas e retorna a média.', 'pratica', 4.00),
(3, 2, 'Implementa a função `contar_aprovados(notas: list, minimo: float = 9.5) -> int` que conta quantos alunos foram aprovados.', 'pratica', 4.00),
(3, 3, 'Implementa a função `nota_mais_alta(notas: list) -> float` e `nota_mais_baixa(notas: list) -> float`.', 'pratica', 4.00),
(3, 4, 'Cria um dicionário que associe o nome de cada aluno à sua nota e ordena-o por nota decrescente.', 'pratica', 5.00),
(3, 5, 'Escreve um script principal que demonstre o uso de todas as funções acima com um conjunto de dados de exemplo.', 'pratica', 3.00),
-- Ficha HTML/CSS
(5, 1, 'Qual é a tag HTML5 correta para definir o cabeçalho principal de uma página? a) <head>  b) <header>  c) <h1>  d) <top>', 'escolha_multipla', 2.00),
(5, 2, 'Escreve o código HTML de um formulário de contacto com campos: nome (texto), email, mensagem (textarea) e botão de envio.', 'desenvolvimento', 6.00),
(5, 3, 'Usando CSS Flexbox, centra horizontal e verticalmente uma div com classe .caixa dentro de um container com classe .container. Escreve o CSS necessário.', 'desenvolvimento', 5.00),
(5, 4, 'Explica a diferença entre display: flex e display: grid. Em que situações usarias cada um?', 'desenvolvimento', 4.00),
(5, 5, 'Verdadeiro ou Falso: (a) A propriedade CSS margin colapsa em elementos inline; (b) Box-sizing: border-box inclui padding e border na largura total; (c) Media queries são usadas apenas para impressão.', 'verdadeiro_falso', 3.00),
-- Ficha BD Modelação
(7, 1, 'Define os conceitos de: Entidade, Atributo, Chave Primária e Chave Estrangeira.', 'desenvolvimento', 4.00),
(7, 2, 'Constrói um Diagrama ER para um sistema de gestão de uma livraria (livros, autores, clientes, encomendas).', 'desenvolvimento', 8.00),
(7, 3, 'Aplica a 1FN, 2FN e 3FN à seguinte tabela não normalizada: Encomenda(ID_Encomenda, Data, Nome_Cliente, Morada, Produto1, Produto2, Produto3, Preco1, Preco2, Preco3).', 'desenvolvimento', 8.00),
-- Teste SQL
(8, 1, 'Escreve uma query SQL que liste todos os formandos com nota final >= 10 na disciplina de Python, ordenados por nota decrescente.', 'pratica', 4.00),
(8, 2, 'Cria uma view chamada vw_medias_disciplina que mostre, para cada disciplina, o nome da disciplina, a média das notas finais e o número de aprovados.', 'pratica', 5.00),
(8, 3, 'Escreve uma query com INNER JOIN que liste o nome do formando, a disciplina e a nota final de todos os formandos aprovados.', 'pratica', 4.00),
(8, 4, 'Explica a diferença entre DELETE, TRUNCATE e DROP em SQL. Em que situações se usa cada um?', 'desenvolvimento', 4.00),
(8, 5, 'O que é uma transação SQL? Demonstra com um exemplo a utilização de BEGIN, COMMIT e ROLLBACK.', 'desenvolvimento', 3.00);

-- ============================================================
-- Classificações – notas geradas de forma realista
-- Escala 0-20, mínimo de aprovação: 9.5
-- ============================================================
INSERT INTO classificacoes (teste_id, formando_id, nota, data_avaliacao) VALUES
-- Teste 1 (Ficha Algoritmos) - formandos 1-20
(1,  1, 16.5, '2024-02-28'), (1,  2, 14.0, '2024-02-28'), (1,  3, 12.5, '2024-02-28'),
(1,  4, 18.0, '2024-02-28'), (1,  5,  9.0, '2024-02-28'), (1,  6, 15.5, '2024-02-28'),
(1,  7, 11.0, '2024-02-28'), (1,  8, 17.0, '2024-02-28'), (1,  9, 13.5, '2024-02-28'),
(1, 10, 10.0, '2024-02-28'), (1, 11, 19.0, '2024-02-28'), (1, 12,  8.5, '2024-02-28'),
(1, 13, 14.5, '2024-02-28'), (1, 14, 16.0, '2024-02-28'), (1, 15, 12.0, '2024-02-28'),
(1, 16, 11.5, '2024-02-28'), (1, 17, 15.0, '2024-02-28'), (1, 18, 13.0, '2024-02-28'),
(1, 19,  7.5, '2024-02-28'), (1, 20, 18.5, '2024-02-28'),
-- Teste 2 (Teste Escrito Fundamentos)
(2,  1, 15.0, '2024-03-06'), (2,  2, 13.5, '2024-03-06'), (2,  3, 11.0, '2024-03-06'),
(2,  4, 17.5, '2024-03-06'), (2,  5,  8.5, '2024-03-06'), (2,  6, 14.5, '2024-03-06'),
(2,  7, 10.5, '2024-03-06'), (2,  8, 16.5, '2024-03-06'), (2,  9, 12.0, '2024-03-06'),
(2, 10,  9.5, '2024-03-06'), (2, 11, 18.5, '2024-03-06'), (2, 12,  7.0, '2024-03-06'),
(2, 13, 13.0, '2024-03-06'), (2, 14, 15.5, '2024-03-06'), (2, 15, 11.5, '2024-03-06'),
(2, 16, 12.5, '2024-03-06'), (2, 17, 14.0, '2024-03-06'), (2, 18, 16.0, '2024-03-06'),
(2, 19,  6.5, '2024-03-06'), (2, 20, 19.0, '2024-03-06'),
-- Teste 3 (Ficha Prática Python)
(3,  1, 14.0, '2024-03-27'), (3,  2, 12.5, '2024-03-27'), (3,  3, 10.0, '2024-03-27'),
(3,  4, 16.0, '2024-03-27'), (3,  5,  9.5, '2024-03-27'), (3,  6, 15.0, '2024-03-27'),
(3,  7, 11.5, '2024-03-27'), (3,  8, 18.0, '2024-03-27'), (3,  9, 13.0, '2024-03-27'),
(3, 10, 10.5, '2024-03-27'), (3, 11, 19.5, '2024-03-27'), (3, 12,  8.0, '2024-03-27'),
(3, 13, 14.5, '2024-03-27'), (3, 14, 16.5, '2024-03-27'), (3, 15, 12.0, '2024-03-27'),
(3, 16, 11.0, '2024-03-27'), (3, 17, 15.5, '2024-03-27'), (3, 18, 17.0, '2024-03-27'),
(3, 19,  7.0, '2024-03-27'), (3, 20, 18.5, '2024-03-27'),
-- Teste 4 (Projeto Python)
(4,  1, 16.0, '2024-04-17'), (4,  2, 13.0, '2024-04-17'), (4,  3, 11.5, '2024-04-17'),
(4,  4, 18.5, '2024-04-17'), (4,  5, 10.0, '2024-04-17'), (4,  6, 15.5, '2024-04-17'),
(4,  7, 12.0, '2024-04-17'), (4,  8, 17.5, '2024-04-17'), (4,  9, 14.0, '2024-04-17'),
(4, 10, 11.0, '2024-04-17'), (4, 11, 20.0, '2024-04-17'), (4, 12,  9.5, '2024-04-17'),
(4, 13, 15.0, '2024-04-17'), (4, 14, 17.0, '2024-04-17'), (4, 15, 13.5, '2024-04-17'),
(4, 16, 12.5, '2024-04-17'), (4, 17, 14.5, '2024-04-17'), (4, 18, 16.5, '2024-04-17'),
(4, 19,  8.5, '2024-04-17'), (4, 20, 19.5, '2024-04-17'),
-- Teste 5 (Ficha HTML/CSS)
(5,  1, 15.5, '2024-05-02'), (5,  2, 14.0, '2024-05-02'), (5,  3, 10.5, '2024-05-02'),
(5,  4, 17.0, '2024-05-02'), (5,  5,  9.0, '2024-05-02'), (5,  6, 16.0, '2024-05-02'),
(5,  7, 12.5, '2024-05-02'), (5,  8, 18.5, '2024-05-02'), (5,  9, 13.5, '2024-05-02'),
(5, 10, 10.0, '2024-05-02'), (5, 11, 19.0, '2024-05-02'), (5, 12,  8.0, '2024-05-02'),
(5, 13, 14.0, '2024-05-02'), (5, 14, 16.5, '2024-05-02'), (5, 15, 11.5, '2024-05-02'),
(5, 16, 13.0, '2024-05-02'), (5, 17, 15.0, '2024-05-02'), (5, 18, 17.5, '2024-05-02'),
(5, 19,  7.5, '2024-05-02'), (5, 20, 18.0, '2024-05-02'),
-- Teste 6 (Projeto Frontend)
(6,  1, 16.5, '2024-05-29'), (6,  2, 14.5, '2024-05-29'), (6,  3, 12.0, '2024-05-29'),
(6,  4, 18.0, '2024-05-29'), (6,  5,  9.5, '2024-05-29'), (6,  6, 16.5, '2024-05-29'),
(6,  7, 13.0, '2024-05-29'), (6,  8, 19.0, '2024-05-29'), (6,  9, 14.5, '2024-05-29'),
(6, 10, 11.5, '2024-05-29'), (6, 11, 20.0, '2024-05-29'), (6, 12, 10.0, '2024-05-29'),
(6, 13, 15.5, '2024-05-29'), (6, 14, 17.5, '2024-05-29'), (6, 15, 12.5, '2024-05-29'),
(6, 16, 13.5, '2024-05-29'), (6, 17, 15.0, '2024-05-29'), (6, 18, 18.5, '2024-05-29'),
(6, 19,  8.0, '2024-05-29'), (6, 20, 19.5, '2024-05-29'),
-- Teste 7 (Ficha Modelação BD)
(7,  1, 14.5, '2024-05-29'), (7,  2, 12.0, '2024-05-29'), (7,  3, 10.0, '2024-05-29'),
(7,  4, 16.5, '2024-05-29'), (7,  5,  8.5, '2024-05-29'), (7,  6, 15.0, '2024-05-29'),
(7,  7, 11.0, '2024-05-29'), (7,  8, 17.5, '2024-05-29'), (7,  9, 13.0, '2024-05-29'),
(7, 10,  9.5, '2024-05-29'), (7, 11, 19.0, '2024-05-29'), (7, 12,  7.5, '2024-05-29'),
(7, 13, 14.0, '2024-05-29'), (7, 14, 16.0, '2024-05-29'), (7, 15, 11.5, '2024-05-29'),
(7, 16, 12.5, '2024-05-29'), (7, 17, 15.5, '2024-05-29'), (7, 18, 17.0, '2024-05-29'),
(7, 19,  6.0, '2024-05-29'), (7, 20, 18.0, '2024-05-29'),
-- Teste 8 (Teste Escrito SQL)
(8,  1, 15.5, '2024-06-05'), (8,  2, 13.0, '2024-06-05'), (8,  3, 11.0, '2024-06-05'),
(8,  4, 17.5, '2024-06-05'), (8,  5,  9.0, '2024-06-05'), (8,  6, 16.0, '2024-06-05'),
(8,  7, 12.0, '2024-06-05'), (8,  8, 18.0, '2024-06-05'), (8,  9, 14.0, '2024-06-05'),
(8, 10, 10.5, '2024-06-05'), (8, 11, 19.5, '2024-06-05'), (8, 12,  8.0, '2024-06-05'),
(8, 13, 14.5, '2024-06-05'), (8, 14, 16.5, '2024-06-05'), (8, 15, 12.5, '2024-06-05'),
(8, 16, 11.5, '2024-06-05'), (8, 17, 15.0, '2024-06-05'), (8, 18, 17.5, '2024-06-05'),
(8, 19,  7.0, '2024-06-05'), (8, 20, 18.5, '2024-06-05');

-- ============================================================
-- Notas finais (média ponderada por disciplina)
-- PROG01: média dos testes 1 e 2  (40% ficha, 60% teste escrito)
-- PROG02: média dos testes 3 e 4  (30% ficha, 70% projeto)
-- WEB01 : média dos testes 5 e 6  (30% ficha, 70% projeto)
-- DB01  : média dos testes 7 e 8  (40% ficha, 60% teste escrito)
-- ============================================================
INSERT INTO notas_finais (formando_id, disciplina_id, turma_id, nota_final)
SELECT
  c1.formando_id,
  1 AS disciplina_id,
  1 AS turma_id,
  ROUND(c1.nota * 0.4 + c2.nota * 0.6, 2) AS nota_final
FROM classificacoes c1
JOIN classificacoes c2 ON c1.formando_id = c2.formando_id
WHERE c1.teste_id = 1 AND c2.teste_id = 2;

INSERT INTO notas_finais (formando_id, disciplina_id, turma_id, nota_final)
SELECT
  c1.formando_id,
  2 AS disciplina_id,
  1 AS turma_id,
  ROUND(c1.nota * 0.3 + c2.nota * 0.7, 2) AS nota_final
FROM classificacoes c1
JOIN classificacoes c2 ON c1.formando_id = c2.formando_id
WHERE c1.teste_id = 3 AND c2.teste_id = 4;

INSERT INTO notas_finais (formando_id, disciplina_id, turma_id, nota_final)
SELECT
  c1.formando_id,
  3 AS disciplina_id,
  1 AS turma_id,
  ROUND(c1.nota * 0.3 + c2.nota * 0.7, 2) AS nota_final
FROM classificacoes c1
JOIN classificacoes c2 ON c1.formando_id = c2.formando_id
WHERE c1.teste_id = 5 AND c2.teste_id = 6;

INSERT INTO notas_finais (formando_id, disciplina_id, turma_id, nota_final)
SELECT
  c1.formando_id,
  5 AS disciplina_id,
  1 AS turma_id,
  ROUND(c1.nota * 0.4 + c2.nota * 0.6, 2) AS nota_final
FROM classificacoes c1
JOIN classificacoes c2 ON c1.formando_id = c2.formando_id
WHERE c1.teste_id = 7 AND c2.teste_id = 8;


-- ============================================================
-- VIEWS ÚTEIS
-- ============================================================

-- Pauta geral por disciplina
CREATE OR REPLACE VIEW vw_pauta AS
SELECT
  f.codigo   AS formando,
  f.nome     AS nome_formando,
  d.nome     AS disciplina,
  nf.nota_final,
  IF(nf.aprovado, 'Aprovado', 'Reprovado') AS resultado
FROM notas_finais nf
JOIN formandos  f ON f.id = nf.formando_id
JOIN disciplinas d ON d.id = nf.disciplina_id
ORDER BY d.nome, nf.nota_final DESC;

-- Estatísticas por disciplina
CREATE OR REPLACE VIEW vw_estatisticas_disciplina AS
SELECT
  d.nome                          AS disciplina,
  COUNT(*)                        AS total_formandos,
  ROUND(AVG(nf.nota_final), 2)    AS media,
  MAX(nf.nota_final)              AS nota_maxima,
  MIN(nf.nota_final)              AS nota_minima,
  SUM(nf.aprovado)                AS aprovados,
  COUNT(*) - SUM(nf.aprovado)     AS reprovados
FROM notas_finais nf
JOIN disciplinas d ON d.id = nf.disciplina_id
GROUP BY d.id, d.nome;

-- Histórico de classificações por formando
CREATE OR REPLACE VIEW vw_historico_formando AS
SELECT
  f.codigo    AS formando,
  f.nome      AS nome_formando,
  d.nome      AS disciplina,
  t.titulo    AS avaliacao,
  t.tipo,
  c.nota,
  c.data_avaliacao
FROM classificacoes c
JOIN formandos   f ON f.id = c.formando_id
JOIN testes      t ON t.id = c.teste_id
JOIN disciplinas d ON d.id = t.disciplina_id
ORDER BY f.codigo, d.nome, c.data_avaliacao;


-- ============================================================
-- FIM DO SCRIPT
-- ============================================================
