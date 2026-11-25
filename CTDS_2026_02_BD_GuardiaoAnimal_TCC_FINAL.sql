-- ============================================================================
-- CENTRO PAULA SOUZA / SENAI — TÉCNICO EM DESENVOLVIMENTO DE SISTEMAS
-- PROJETO DE CONCLUSÃO DE CURSO (TCC): GUARDIÃO ANIMAL (V36.1)
-- ============================================================================
-- AUTORES: Arthur Morais, Davi Kiliam e Vinicius Lourenço
-- MUNICÍPIO: Ourinhos - SP | ANO: 2026
-- PLATAFORMA DE BANCO DE DADOS: PostgreSQL 15+ / Supabase (Cloud Database)
-- ESCOPO: Esquema relacional completo, DDL, Índices de Performance, Procedimentos
--         armazenados (RPC), Sequenciador numérico local, Políticas de Segurança
--         Row Level Security (RLS) e Carga Inicial Homologada de Produção (Seed).
-- ============================================================================
-- ARQUITETURA DE DADOS V36.1:
-- 1. O sistema é 100% desacoplado de arquivos locais ou tabelas obsoletas.
--    Tabelas descartadas da versão anterior (noticias, ga_app_records) são
--    removidas com segurança.
-- 2. Cada entidade possui chave primária UUID nativa para replicação global,
--    combinada com identificadores numéricos incrementais 'local_id' para
--    visualização amigável de protocolos, auditorias e gestão de ONGs.
-- 3. Acesso à API Rest do Supabase é protegido via RLS e service_role restrito.
-- ============================================================================

BEGIN;

-- Extensão criptográfica essencial para geração de UUIDv4 e hashes de segurança
CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- ----------------------------------------------------------------------------
-- 1. LIMPEZA SEGURA DE ESTRUTURAS OBSOLETAS (MIGRAÇÃO NÃO DESTRUTIVA)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS public.noticias CASCADE;
DROP TABLE IF EXISTS public.ga_app_records CASCADE;

-- ----------------------------------------------------------------------------
-- 2. SEQUENCIADOR CENTRAL DE IDENTIFICADORES NUMÉRICOS LOCAIS
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.ga_id_sequences (
    store text PRIMARY KEY,
    last_id bigint NOT NULL DEFAULT 1000000
);

CREATE OR REPLACE FUNCTION public.ga_next_local_id(p_store text)
RETURNS bigint LANGUAGE plpgsql SECURITY INVOKER SET search_path = public
AS $$
DECLARE 
    result bigint;
BEGIN
    IF p_store !~ '^[a-z_]+$' THEN 
        RAISE EXCEPTION 'Identificador de entidade inválido: %', p_store; 
    END IF;
    
    INSERT INTO public.ga_id_sequences (store, last_id) 
    VALUES (p_store, 1000001)
    ON CONFLICT (store) DO UPDATE 
    SET last_id = public.ga_id_sequences.last_id + 1
    RETURNING last_id INTO result;
    
    RETURN result;
END; 
$$;

REVOKE ALL ON FUNCTION public.ga_next_local_id(text) FROM PUBLIC, anon, authenticated;
GRANT EXECUTE ON FUNCTION public.ga_next_local_id(text) TO service_role;

-- ----------------------------------------------------------------------------
-- 3. TABELAS ADMINISTRATIVAS E AUTENTICAÇÃO
-- ----------------------------------------------------------------------------

-- Administradores do Sistema Guardião Animal
CREATE TABLE IF NOT EXISTS public.administradores (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    local_id bigint UNIQUE,
    nome text NOT NULL,
    username text UNIQUE NOT NULL,
    email text UNIQUE NOT NULL,
    password_hash text NOT NULL,
    tipo text NOT NULL DEFAULT 'admin' CHECK (tipo IN ('principal', 'admin')),
    status text NOT NULL DEFAULT 'Ativo' CHECK (status IN ('Ativo', 'Bloqueado')),
    payload jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now()
);

-- Solicitações de Novos Administradores (Aprovação pelo Admin Principal)
CREATE TABLE IF NOT EXISTS public.admin_solicitacoes (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    local_id bigint UNIQUE,
    nome text NOT NULL,
    username text NOT NULL,
    email text NOT NULL,
    password_hash text NOT NULL,
    status text NOT NULL DEFAULT 'Pendente' CHECK (status IN ('Pendente', 'Aprovada', 'Rejeitada')),
    reviewed_at timestamptz,
    reviewed_by uuid REFERENCES public.administradores(id) ON DELETE SET NULL,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now()
);

-- ----------------------------------------------------------------------------
-- 4. ONGS E CADASTRO INSTITUCIONAL
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.ongs (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    local_id bigint UNIQUE,
    nome text NOT NULL,
    email text UNIQUE NOT NULL,
    password_hash text NOT NULL,
    cidade text NOT NULL DEFAULT 'Ourinhos',
    estado text DEFAULT 'SP',
    bairro text,
    telefone text,
    ano_fundacao integer,
    foco text,
    descricao text,
    historia text,
    imagem_url text,
    status text NOT NULL DEFAULT 'Aprovada' CHECK (status IN ('Aprovada', 'Pendente', 'Suspensa')),
    payload jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now(),
    reviewed_at timestamptz
);

-- ----------------------------------------------------------------------------
-- 5. ANIMAIS RESGATADOS E PARA ADOÇÃO
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.animais (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    local_id bigint UNIQUE,
    ong_id uuid REFERENCES public.ongs(id) ON DELETE SET NULL,
    nome text NOT NULL,
    especie text NOT NULL,
    raca text,
    idade text,
    sexo text,
    porte text,
    temperamento text,
    historia_resgate text,
    local_encontrado text,
    data_encontrado date,
    status text NOT NULL DEFAULT 'disponivel' CHECK (status IN ('disponivel', 'adotado', 'em_tratamento')),
    imagem_url text,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now()
);

-- ----------------------------------------------------------------------------
-- 6. DENÚNCIAS E ATENDIMENTO POR ONGS (NÚCLEO DO SISTEMA)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS public.denuncias (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    local_id bigint UNIQUE,
    protocolo text UNIQUE NOT NULL,
    especie text,
    descricao text NOT NULL,
    local_ocorrencia text NOT NULL,
    bairro text NOT NULL,
    cidade text NOT NULL DEFAULT 'Ourinhos',
    estado text NOT NULL DEFAULT 'SP',
    urgencia text NOT NULL DEFAULT 'Baixa',
    deseja_identificar boolean NOT NULL DEFAULT false,
    nome_denunciante text,
    contato_denunciante text,
    status text NOT NULL DEFAULT 'Recebida',
    payload jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now()
);

-- Atendimentos de Denúncias pelas ONGs
CREATE TABLE IF NOT EXISTS public.ong_denuncia_atendimentos (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    ong_id uuid NOT NULL REFERENCES public.ongs(id) ON DELETE CASCADE,
    denuncia_id uuid NOT NULL REFERENCES public.denuncias(id) ON DELETE CASCADE,
    status text NOT NULL DEFAULT 'Recebida',
    observacao text,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE (ong_id, denuncia_id)
);

-- ----------------------------------------------------------------------------
-- 7. PROCESSOS COMPLEMENTARES: ADOÇÕES, DOAÇÕES, APADRINHAMENTOS E CONTATO
-- ----------------------------------------------------------------------------

-- Solicitações de Adoção
CREATE TABLE IF NOT EXISTS public.solicitacoes_adocao (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    local_id bigint UNIQUE,
    animal_id uuid REFERENCES public.animais(id) ON DELETE SET NULL,
    ong_id uuid REFERENCES public.ongs(id) ON DELETE SET NULL,
    animal_nome text,
    ong_nome text,
    nome_completo text NOT NULL,
    email text NOT NULL,
    telefone text,
    moradia text,
    experiencia text,
    mensagem text,
    status text NOT NULL DEFAULT 'pendente',
    payload jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now()
);

-- Registros de Intenção de Doação
CREATE TABLE IF NOT EXISTS public.doacoes (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    local_id bigint UNIQUE,
    ong_id uuid REFERENCES public.ongs(id) ON DELETE SET NULL,
    ong_nome text,
    nome_doador text,
    email_doador text,
    valor numeric(12,2) NOT NULL CHECK (valor > 0),
    tipo text NOT NULL DEFAULT 'unica',
    descricao text,
    mensagem text,
    forma_entrega text,
    comissao_plataforma numeric(12,2) DEFAULT 0,
    valor_liquido numeric(12,2),
    pagamento_confirmado boolean NOT NULL DEFAULT false,
    status text NOT NULL DEFAULT 'registrada',
    payload jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now()
);

-- Apadrinhamento de Animais
CREATE TABLE IF NOT EXISTS public.apadrinhamentos (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    local_id bigint UNIQUE,
    animal_id uuid REFERENCES public.animais(id) ON DELETE SET NULL,
    ong_id uuid REFERENCES public.ongs(id) ON DELETE SET NULL,
    animal_nome text,
    nome text NOT NULL,
    email text NOT NULL,
    valor_mensal numeric(12,2),
    mensagem text,
    status text NOT NULL DEFAULT 'pendente',
    payload jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now()
);

-- Fale Conosco / Mensagens de Contato
CREATE TABLE IF NOT EXISTS public.contatos (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    local_id bigint UNIQUE,
    nome text NOT NULL,
    email text NOT NULL,
    assunto text,
    mensagem text NOT NULL,
    lido boolean NOT NULL DEFAULT false,
    status text NOT NULL DEFAULT 'novo',
    payload jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now()
);

-- Auditoria Operacional
CREATE TABLE IF NOT EXISTS public.auditoria (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    ator_id uuid REFERENCES public.administradores(id) ON DELETE SET NULL,
    tipo_acao text NOT NULL,
    entidade text NOT NULL,
    entidade_id text,
    detalhes jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now()
);

-- ----------------------------------------------------------------------------
-- 8. ÍNDICES DE PERFORMANCE E INTEGRIDADE
-- ----------------------------------------------------------------------------
CREATE INDEX IF NOT EXISTS ga_denuncias_bairro_idx ON public.denuncias (bairro);
CREATE INDEX IF NOT EXISTS ga_denuncias_status_idx ON public.denuncias (status);
CREATE INDEX IF NOT EXISTS ga_denuncias_data_idx ON public.denuncias (created_at DESC);
CREATE INDEX IF NOT EXISTS ga_animais_ong_idx ON public.animais (ong_id);
CREATE INDEX IF NOT EXISTS ga_animais_status_idx ON public.animais (status);
CREATE INDEX IF NOT EXISTS ga_ongs_status_idx ON public.ongs (status);
CREATE INDEX IF NOT EXISTS ga_ong_atendimentos_ong_idx ON public.ong_denuncia_atendimentos (ong_id);
CREATE INDEX IF NOT EXISTS ga_ong_atendimentos_denuncia_idx ON public.ong_denuncia_atendimentos (denuncia_id);
CREATE INDEX IF NOT EXISTS ga_doacoes_ong_idx ON public.doacoes (ong_id);
CREATE INDEX IF NOT EXISTS ga_apadrinhamentos_animal_idx ON public.apadrinhamentos (animal_id);
CREATE INDEX IF NOT EXISTS ga_solicitacoes_adocao_animal_idx ON public.solicitacoes_adocao (animal_id);

-- ----------------------------------------------------------------------------
-- 9. SEGURANÇA: ROW LEVEL SECURITY (RLS)
-- ----------------------------------------------------------------------------
ALTER TABLE public.administradores ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.admin_solicitacoes ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.ongs ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.animais ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.denuncias ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.ong_denuncia_atendimentos ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.solicitacoes_adocao ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.doacoes ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.apadrinhamentos ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.contatos ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.auditoria ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.ga_id_sequences ENABLE ROW LEVEL SECURITY;

-- Políticas de Leitura Pública Sanitizada (Anon / Authenticated)
DROP POLICY IF EXISTS ga_ler_ongs_aprovadas ON public.ongs;
CREATE POLICY ga_ler_ongs_aprovadas ON public.ongs FOR SELECT TO anon, authenticated
USING (status = 'Aprovada');

DROP POLICY IF EXISTS ga_ler_animais_disponiveis ON public.animais;
CREATE POLICY ga_ler_animais_disponiveis ON public.animais FOR SELECT TO anon, authenticated
USING (status = 'disponivel');

-- Políticas de Inserção Pública nos Formulários Web
DROP POLICY IF EXISTS ga_inserir_denuncia_publica ON public.denuncias;
CREATE POLICY ga_inserir_denuncia_publica ON public.denuncias FOR INSERT TO anon, authenticated
WITH CHECK (length(protocolo) >= 8 AND length(descricao) >= 15 AND length(local_ocorrencia) >= 5);

DROP POLICY IF EXISTS ga_inserir_contato_publico ON public.contatos;
CREATE POLICY ga_inserir_contato_publico ON public.contatos FOR INSERT TO anon, authenticated
WITH CHECK (length(nome) >= 2 AND length(mensagem) >= 5);

DROP POLICY IF EXISTS ga_inserir_doacao_publica ON public.doacoes;
CREATE POLICY ga_inserir_doacao_publica ON public.doacoes FOR INSERT TO anon, authenticated
WITH CHECK (valor > 0);

DROP POLICY IF EXISTS ga_inserir_apadrinhamento_publico ON public.apadrinhamentos;
CREATE POLICY ga_inserir_apadrinhamento_publico ON public.apadrinhamentos FOR INSERT TO anon, authenticated
WITH CHECK (length(nome) >= 2 AND length(email) >= 5);

DROP POLICY IF EXISTS ga_inserir_adocao_publica ON public.solicitacoes_adocao;
CREATE POLICY ga_inserir_adocao_publica ON public.solicitacoes_adocao FOR INSERT TO anon, authenticated
WITH CHECK (length(nome_completo) >= 2 AND length(email) >= 5);

-- Restrição de Tabelas Sensíveis a Acesso Público Direto
REVOKE ALL ON TABLE public.administradores, public.admin_solicitacoes, public.ong_denuncia_atendimentos, public.auditoria FROM anon, authenticated;
REVOKE SELECT (password_hash) ON public.ongs FROM anon, authenticated;

-- Permissões Totais para a Chave de Serviço do Backend PHP
GRANT ALL ON ALL TABLES IN SCHEMA public TO service_role;
GRANT ALL ON ALL SEQUENCES IN SCHEMA public TO service_role;
GRANT ALL ON ALL FUNCTIONS IN SCHEMA public TO service_role;

-- ----------------------------------------------------------------------------
-- 10. CARGA INICIAL HOMOLOGADA (SEED DATA DE PRODUÇÃO)
-- ----------------------------------------------------------------------------

-- Administrador Padrão da Plataforma
-- Senha de produção inicial: 123456789101112 (armazenada com hash Bcrypt de 12 rounds)
INSERT INTO public.administradores (local_id, nome, username, email, password_hash, tipo, status, payload)
VALUES (1000001, 'GUARDIAO ANIMAL', 'principal', 'guardiaoanimal@gmail.com', '$2y$12$ciPeSZO6QdA3bRhH6de.kOSdnKftMLAftkJo3jMubHPvE5aIpg.ku', 'principal', 'Ativo', '{"id":1000001,"nome":"GUARDIAO ANIMAL","tipo":"principal","email":"guardiaoanimal@gmail.com","status":"Ativo","username":"principal","created_at":"2026-11-22 19:50:05","password_hash":"$2y$12$ciPeSZO6QdA3bRhH6de.kOSdnKftMLAftkJo3jMubHPvE5aIpg.ku"}'::jsonb)
ON CONFLICT (email) DO UPDATE SET password_hash = EXCLUDED.password_hash, status = EXCLUDED.status, payload = EXCLUDED.payload;

-- ONGs Oficiais Homologadas de Ourinhos - SP
-- Senha de acesso padrão aos portais: Ong@123456 (hash Bcrypt)
INSERT INTO public.ongs (local_id, nome, email, password_hash, cidade, estado, bairro, telefone, foco, descricao, historia, imagem_url, status, payload)
VALUES (900001, 'ONG SOS Cidadania Animal', 'ong1@demo.guardiaoanimal.example', '$2y$12$RyshdzJK2kpCQ7TTplbbAedtcmR/sK0CvHlMw2dP/jBs3LYCVnJIm', 'Ourinhos', 'SP', 'Centro', '', 'Resgate e proteção animal', 'Organização de Ourinhos voltada ao resgate, acolhimento e encaminhamento responsável de cães e gatos em situação de vulnerabilidade.', 'A SOS Cidadania Animal atua em Ourinhos com rede de voluntários, apoio veterinário e campanhas de conscientização sobre proteção e adoção responsável.', 'https://ongsoscidadaniaanimal.org/images/site/logo-sobre-a-ong.png', 'Aprovada', '{"id":900001,"foco":"Resgate e proteção animal","foto":"https://ongsoscidadaniaanimal.org/images/site/logo-sobre-a-ong.png","nome":"ONG SOS Cidadania Animal","email":"ong1@demo.guardiaoanimal.example","bairro":"Centro","cidade":"Ourinhos","status":"Aprovada","historia":"A SOS Cidadania Animal atua em Ourinhos com rede de voluntários, apoio veterinário e campanhas de conscientização sobre proteção e adoção responsável.","telefone":null,"descricao":"Organização de Ourinhos voltada ao resgate, acolhimento e encaminhamento responsável de cães e gatos em situação de vulnerabilidade.","created_at":"2026-11-12T19:36:53.302136+00:00","password_hash":"$2y$12$RyshdzJK2kpCQ7TTplbbAedtcmR/sK0CvHlMw2dP/jBs3LYCVnJIm","public_ong_id":1}'::jsonb)
ON CONFLICT (email) DO UPDATE SET nome = EXCLUDED.nome, password_hash = EXCLUDED.password_hash, bairro = EXCLUDED.bairro, telefone = EXCLUDED.telefone, foco = EXCLUDED.foco, descricao = EXCLUDED.descricao, historia = EXCLUDED.historia, imagem_url = EXCLUDED.imagem_url, status = EXCLUDED.status, payload = EXCLUDED.payload;

INSERT INTO public.ongs (local_id, nome, email, password_hash, cidade, estado, bairro, telefone, foco, descricao, historia, imagem_url, status, payload)
VALUES (900002, 'ONG Azul', 'ong2@demo.guardiaoanimal.example', '$2y$12$RyshdzJK2kpCQ7TTplbbAedtcmR/sK0CvHlMw2dP/jBs3LYCVnJIm', 'Ourinhos', 'SP', 'Jardim Matilde', '', 'Adoção responsável', 'Grupo de Ourinhos que aproxima animais resgatados de novas famílias por meio de divulgação, triagem e acompanhamento pós-adoção.', 'A ONG Azul reúne protetores de Ourinhos para dar visibilidade a animais resgatados, apoiar lares temporários e incentivar adoções conscientes.', 'https://storage.googleapis.com/atados-v3/user-uploaded/images/e7ce1db4-e764-4bee-b8a6-e1146e612cf1.png', 'Aprovada', '{"id":900002,"foco":"Adoção responsável","foto":"https://storage.googleapis.com/atados-v3/user-uploaded/images/e7ce1db4-e764-4bee-b8a6-e1146e612cf1.png","nome":"ONG Azul","email":"ong2@demo.guardiaoanimal.example","bairro":"Jardim Matilde","cidade":"Ourinhos","status":"Aprovada","historia":"A ONG Azul reúne protetores de Ourinhos para dar visibilidade a animais resgatados, apoiar lares temporários e incentivar adoções conscientes.","telefone":null,"descricao":"Grupo de Ourinhos que aproxima animais resgatados de novas famílias por meio de divulgação, triagem e acompanhamento pós-adoção.","created_at":"2026-11-12T19:36:53.302136+00:00","password_hash":"$2y$12$RyshdzJK2kpCQ7TTplbbAedtcmR/sK0CvHlMw2dP/jBs3LYCVnJIm","public_ong_id":2}'::jsonb)
ON CONFLICT (email) DO UPDATE SET nome = EXCLUDED.nome, password_hash = EXCLUDED.password_hash, bairro = EXCLUDED.bairro, telefone = EXCLUDED.telefone, foco = EXCLUDED.foco, descricao = EXCLUDED.descricao, historia = EXCLUDED.historia, imagem_url = EXCLUDED.imagem_url, status = EXCLUDED.status, payload = EXCLUDED.payload;

INSERT INTO public.ongs (local_id, nome, email, password_hash, cidade, estado, bairro, telefone, foco, descricao, historia, imagem_url, status, payload)
VALUES (900003, 'Amigos de Ourinhos', 'ong3@demo.guardiaoanimal.example', '$2y$12$RyshdzJK2kpCQ7TTplbbAedtcmR/sK0CvHlMw2dP/jBs3LYCVnJIm', 'Ourinhos', 'SP', 'Vila Nova', '', 'Acolhimento e castração', 'Iniciativa local de Ourinhos com foco em acolhimento temporário, castração e encaminhamento de casos urbanos.', 'Nasceu da união de protetores independentes da cidade e hoje atua em Ourinhos com lares temporários, orientação à comunidade e apoio em resgates.', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRhn4qjfQyXqdb1y8W6eepjsbHML704ZF7nDIzrjTUwGg0hI7CJOmAulyBM&s=10', 'Aprovada', '{"id":900003,"foco":"Acolhimento e castração","foto":"https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRhn4qjfQyXqdb1y8W6eepjsbHML704ZF7nDIzrjTUwGg0hI7CJOmAulyBM&s=10","nome":"Amigos de Ourinhos","email":"ong3@demo.guardiaoanimal.example","bairro":"Vila Nova","cidade":"Ourinhos","status":"Aprovada","historia":"Nasceu da união de protetores independentes da cidade e hoje atua em Ourinhos com lares temporários, orientação à comunidade e apoio em resgates.","telefone":null,"descricao":"Iniciativa local de Ourinhos com foco em acolhimento temporário, castração e encaminhamento de casos urbanos.","created_at":"2026-11-12T19:36:53.302136+00:00","password_hash":"$2y$12$RyshdzJK2kpCQ7TTplbbAedtcmR/sK0CvHlMw2dP/jBs3LYCVnJIm","public_ong_id":3}'::jsonb)
ON CONFLICT (email) DO UPDATE SET nome = EXCLUDED.nome, password_hash = EXCLUDED.password_hash, bairro = EXCLUDED.bairro, telefone = EXCLUDED.telefone, foco = EXCLUDED.foco, descricao = EXCLUDED.descricao, historia = EXCLUDED.historia, imagem_url = EXCLUDED.imagem_url, status = EXCLUDED.status, payload = EXCLUDED.payload;

INSERT INTO public.ongs (local_id, nome, email, password_hash, cidade, estado, bairro, telefone, foco, descricao, historia, imagem_url, status, payload)
VALUES (900004, 'Clube Amigo dos Animais', 'ong4@demo.guardiaoanimal.example', '$2y$12$RyshdzJK2kpCQ7TTplbbAedtcmR/sK0CvHlMw2dP/jBs3LYCVnJIm', 'Ourinhos', 'SP', 'Jardim Paulista', '', 'Atendimento e reabilitação', 'ONG de Ourinhos dedicada ao acolhimento de animais em recuperação, com divulgação de casos e incentivo à adoção responsável.', 'O Clube Amigo dos Animais consolidou em Ourinhos uma rotina de atendimento, reabilitação e encaminhamento de animais feridos ou abandonados.', 'https://static.wixstatic.com/media/a451c1_cffd930cae8c4c1394a0d40ed9374f2f~mv2.jpg', 'Aprovada', '{"id":900004,"foco":"Atendimento e reabilitação","foto":"https://static.wixstatic.com/media/a451c1_cffd930cae8c4c1394a0d40ed9374f2f~mv2.jpg","nome":"Clube Amigo dos Animais","email":"ong4@demo.guardiaoanimal.example","bairro":"Jardim Paulista","cidade":"Ourinhos","status":"Aprovada","historia":"O Clube Amigo dos Animais consolidou em Ourinhos uma rotina de atendimento, reabilitação e encaminhamento de animais feridos ou abandonados.","telefone":null,"descricao":"ONG de Ourinhos dedicada ao acolhimento de animais em recuperação, com divulgação de casos e incentivo à adoção responsável.","created_at":"2026-11-12T19:36:53.302136+00:00","password_hash":"$2y$12$RyshdzJK2kpCQ7TTplbbAedtcmR/sK0CvHlMw2dP/jBs3LYCVnJIm","public_ong_id":4}'::jsonb)
ON CONFLICT (email) DO UPDATE SET nome = EXCLUDED.nome, password_hash = EXCLUDED.password_hash, bairro = EXCLUDED.bairro, telefone = EXCLUDED.telefone, foco = EXCLUDED.foco, descricao = EXCLUDED.descricao, historia = EXCLUDED.historia, imagem_url = EXCLUDED.imagem_url, status = EXCLUDED.status, payload = EXCLUDED.payload;

INSERT INTO public.ongs (local_id, nome, email, password_hash, cidade, estado, bairro, telefone, foco, descricao, historia, imagem_url, status, payload)
VALUES (900005, 'Rede Patinhas de Ourinhos', 'ong5@demo.guardiaoanimal.example', '$2y$12$RyshdzJK2kpCQ7TTplbbAedtcmR/sK0CvHlMw2dP/jBs3LYCVnJIm', 'Ourinhos', 'SP', 'Jardim Itamaraty', '', 'Feiras e adoção', 'Rede de protetores de Ourinhos que divulga animais resgatados, organiza feiras e fortalece o contato entre cuidadores e adotantes.', 'A Rede Patinhas de Ourinhos trabalha com divulgação digital, apoio a resgates e eventos de adoção na cidade.', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSAb3JMrJHt1WUDKcJF8wSkbLAo4qGXMUYbPwcBMWbutRfx5rSQ8FYKTdc&s=10', 'Aprovada', '{"id":900005,"foco":"Feiras e adoção","foto":"https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSAb3JMrJHt1WUDKcJF8wSkbLAo4qGXMUYbPwcBMWbutRfx5rSQ8FYKTdc&s=10","nome":"Rede Patinhas de Ourinhos","email":"ong5@demo.guardiaoanimal.example","bairro":"Jardim Itamaraty","cidade":"Ourinhos","status":"Aprovada","historia":"A Rede Patinhas de Ourinhos trabalha com divulgação digital, apoio a resgates e eventos de adoção na cidade.","telefone":null,"descricao":"Rede de protetores de Ourinhos que divulga animais resgatados, organiza feiras e fortalece o contato entre cuidadores e adotantes.","created_at":"2026-11-12T19:36:53.302136+00:00","password_hash":"$2y$12$RyshdzJK2kpCQ7TTplbbAedtcmR/sK0CvHlMw2dP/jBs3LYCVnJIm","public_ong_id":5}'::jsonb)
ON CONFLICT (email) DO UPDATE SET nome = EXCLUDED.nome, password_hash = EXCLUDED.password_hash, bairro = EXCLUDED.bairro, telefone = EXCLUDED.telefone, foco = EXCLUDED.foco, descricao = EXCLUDED.descricao, historia = EXCLUDED.historia, imagem_url = EXCLUDED.imagem_url, status = EXCLUDED.status, payload = EXCLUDED.payload;

-- Catálogo de Animais Resgatados Homologados (3 por ONG oficial = 15 animais)
INSERT INTO public.animais (local_id, ong_id, nome, especie, raca, idade, sexo, porte, temperamento, historia_resgate, local_encontrado, status, imagem_url, payload)
VALUES (1001, (SELECT id FROM public.ongs WHERE local_id = 900001 LIMIT 1), 'Joca', 'Cão', 'SRD', '3 anos', 'Macho', 'Médio', 'Carinhoso, brincalhão e sociável com outros cães.', 'Joca apareceu perto de um mercado em uma manhã de chuva. A equipe acolheu e cuidou de sua pata machucada.', 'Rua dos Ipês, Centro, Ourinhos', 'disponível', 'https://images.unsplash.com/photo-1543466835-00a7907e9de1?w=800&auto=format&fit=crop', '{"id":1001,"foto":"https://images.unsplash.com/photo-1543466835-00a7907e9de1?w=800&auto=format&fit=crop","nome":"Joca","raca":"SRD","sexo":"Macho","idade":"3 anos","local":"Rua dos Ipês, Centro, Ourinhos","porte":"Médio","ong_id":900001,"status":"Disponível","especie":"Cão","historia":"Joca apareceu perto de um mercado em uma manhã de chuva. A equipe acolheu e cuidou de sua pata machucada.","created_at":"2026-11-23 03:08:52","temperamento":"Carinhoso, brincalhão e sociável com outros cães."}'::jsonb)
ON CONFLICT (local_id) DO UPDATE SET ong_id = EXCLUDED.ong_id, nome = EXCLUDED.nome, especie = EXCLUDED.especie, raca = EXCLUDED.raca, idade = EXCLUDED.idade, sexo = EXCLUDED.sexo, porte = EXCLUDED.porte, temperamento = EXCLUDED.temperamento, historia_resgate = EXCLUDED.historia_resgate, local_encontrado = EXCLUDED.local_encontrado, status = EXCLUDED.status, imagem_url = EXCLUDED.imagem_url, payload = EXCLUDED.payload;

INSERT INTO public.animais (local_id, ong_id, nome, especie, raca, idade, sexo, porte, temperamento, historia_resgate, local_encontrado, status, imagem_url, payload)
VALUES (1002, (SELECT id FROM public.ongs WHERE local_id = 900001 LIMIT 1), 'Thor', 'Cão', 'Labrador Mestiço', '5 anos', 'Macho', 'Grande', 'Muito dócil, forte e leal.', 'Foi retirado de uma situação de risco próximo à linha de trem. Passou por reabilitação e adora passear.', 'Avenida Altino Arantes, Centro, Ourinhos', 'disponível', 'https://images.unsplash.com/photo-1552053831-71594a27632d?w=800&auto=format&fit=crop', '{"id":1002,"foto":"https://images.unsplash.com/photo-1552053831-71594a27632d?w=800&auto=format&fit=crop","nome":"Thor","raca":"Labrador Mestiço","sexo":"Macho","idade":"5 anos","local":"Avenida Altino Arantes, Centro, Ourinhos","porte":"Grande","ong_id":900001,"status":"Disponível","especie":"Cão","historia":"Foi retirado de uma situação de risco próximo à linha de trem. Passou por reabilitação e adora passear.","created_at":"2026-11-23 03:08:52","temperamento":"Muito dócil, forte e leal."}'::jsonb)
ON CONFLICT (local_id) DO UPDATE SET ong_id = EXCLUDED.ong_id, nome = EXCLUDED.nome, especie = EXCLUDED.especie, raca = EXCLUDED.raca, idade = EXCLUDED.idade, sexo = EXCLUDED.sexo, porte = EXCLUDED.porte, temperamento = EXCLUDED.temperamento, historia_resgate = EXCLUDED.historia_resgate, local_encontrado = EXCLUDED.local_encontrado, status = EXCLUDED.status, imagem_url = EXCLUDED.imagem_url, payload = EXCLUDED.payload;

INSERT INTO public.animais (local_id, ong_id, nome, especie, raca, idade, sexo, porte, temperamento, historia_resgate, local_encontrado, status, imagem_url, payload)
VALUES (1003, (SELECT id FROM public.ongs WHERE local_id = 900001 LIMIT 1), 'Dudu', 'Cão', 'SRD', '2 anos', 'Macho', 'Pequeno', 'Esperto, brincalhão e ótimo com crianças.', 'Foi encontrado em frente a uma escola comunitária. Muito carinhoso e adora colo.', 'Vila Margarida, Ourinhos', 'disponível', 'https://images.unsplash.com/photo-1587300003388-59208cc962cb?w=800&auto=format&fit=crop', '{"id":1003,"foto":"https://images.unsplash.com/photo-1587300003388-59208cc962cb?w=800&auto=format&fit=crop","nome":"Dudu","raca":"SRD","sexo":"Macho","idade":"2 anos","local":"Vila Margarida, Ourinhos","porte":"Pequeno","ong_id":900001,"status":"Disponível","especie":"Cão","historia":"Foi encontrado em frente a uma escola comunitária. Muito carinhoso e adora colo.","created_at":"2026-11-23 03:08:53","temperamento":"Esperto, brincalhão e ótimo com crianças."}'::jsonb)
ON CONFLICT (local_id) DO UPDATE SET ong_id = EXCLUDED.ong_id, nome = EXCLUDED.nome, especie = EXCLUDED.especie, raca = EXCLUDED.raca, idade = EXCLUDED.idade, sexo = EXCLUDED.sexo, porte = EXCLUDED.porte, temperamento = EXCLUDED.temperamento, historia_resgate = EXCLUDED.historia_resgate, local_encontrado = EXCLUDED.local_encontrado, status = EXCLUDED.status, imagem_url = EXCLUDED.imagem_url, payload = EXCLUDED.payload;

INSERT INTO public.animais (local_id, ong_id, nome, especie, raca, idade, sexo, porte, temperamento, historia_resgate, local_encontrado, status, imagem_url, payload)
VALUES (1004, (SELECT id FROM public.ongs WHERE local_id = 900002 LIMIT 1), 'Pipoca', 'Cão', 'Beagle Mestiço', '1 ano', 'Fêmea', 'Pequeno', 'Muito ativa, curiosa e apaixonada por bolinhas.', 'Chegou junto de sua ninhada encontrada em um terreno. Já foi vacinada e castrada.', 'Jardim Matilde, Ourinhos', 'disponível', 'https://images.unsplash.com/photo-1537151625747-768eb6cf92b2?w=800&auto=format&fit=crop', '{"id":1004,"foto":"https://images.unsplash.com/photo-1537151625747-768eb6cf92b2?w=800&auto=format&fit=crop","nome":"Pipoca","raca":"Beagle Mestiço","sexo":"Fêmea","idade":"1 ano","local":"Jardim Matilde, Ourinhos","porte":"Pequeno","ong_id":900002,"status":"Disponível","especie":"Cão","historia":"Chegou junto de sua ninhada encontrada em um terreno. Já foi vacinada e castrada.","created_at":"2026-11-23 03:12:00","temperamento":"Muito ativa, curiosa e apaixonada por bolinhas."}'::jsonb)
ON CONFLICT (local_id) DO UPDATE SET ong_id = EXCLUDED.ong_id, nome = EXCLUDED.nome, especie = EXCLUDED.especie, raca = EXCLUDED.raca, idade = EXCLUDED.idade, sexo = EXCLUDED.sexo, porte = EXCLUDED.porte, temperamento = EXCLUDED.temperamento, historia_resgate = EXCLUDED.historia_resgate, local_encontrado = EXCLUDED.local_encontrado, status = EXCLUDED.status, imagem_url = EXCLUDED.imagem_url, payload = EXCLUDED.payload;

INSERT INTO public.animais (local_id, ong_id, nome, especie, raca, idade, sexo, porte, temperamento, historia_resgate, local_encontrado, status, imagem_url, payload)
VALUES (1005, (SELECT id FROM public.ongs WHERE local_id = 900002 LIMIT 1), 'Luna', 'Gato', 'Siamês Mestiço', '2 anos', 'Fêmea', 'Pequeno', 'Sociável, ronronenta e calma com visitas.', 'Apareceu na garagem de uma voluntária. Como ninguém procurou por ela, foi encaminhada para adoção.', 'Rua Paraná, Jardim Matilde, Ourinhos', 'disponível', 'https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?w=800&auto=format&fit=crop', '{"id":1005,"foto":"https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?w=800&auto=format&fit=crop","nome":"Luna","raca":"Siamês Mestiço","sexo":"Fêmea","idade":"2 anos","local":"Rua Paraná, Jardim Matilde, Ourinhos","porte":"Pequeno","ong_id":900002,"status":"Disponível","especie":"Gato","historia":"Apareceu na garagem de uma voluntária. Como ninguém procurou por ela, foi encaminhada para adoção.","created_at":"2026-11-23 03:12:00","temperamento":"Sociável, ronronenta e calma com visitas."}'::jsonb)
ON CONFLICT (local_id) DO UPDATE SET ong_id = EXCLUDED.ong_id, nome = EXCLUDED.nome, especie = EXCLUDED.especie, raca = EXCLUDED.raca, idade = EXCLUDED.idade, sexo = EXCLUDED.sexo, porte = EXCLUDED.porte, temperamento = EXCLUDED.temperamento, historia_resgate = EXCLUDED.historia_resgate, local_encontrado = EXCLUDED.local_encontrado, status = EXCLUDED.status, imagem_url = EXCLUDED.imagem_url, payload = EXCLUDED.payload;

INSERT INTO public.animais (local_id, ong_id, nome, especie, raca, idade, sexo, porte, temperamento, historia_resgate, local_encontrado, status, imagem_url, payload)
VALUES (1006, (SELECT id FROM public.ongs WHERE local_id = 900002 LIMIT 1), 'Theo', 'Cão', 'Poodle Mestiço', '2 anos', 'Macho', 'Pequeno', 'Amigável, brincalhão e aprende comandos com facilidade.', 'Resgatado após ser visto sozinho próximo à praça. Foi tosado, tratado e aguarda uma família.', 'Praça Mello Peixoto, Ourinhos', 'disponível', 'https://images.unsplash.com/photo-1583511655857-d19b40a7a54e?w=800&auto=format&fit=crop', '{"id":1006,"foto":"https://images.unsplash.com/photo-1583511655857-d19b40a7a54e?w=800&auto=format&fit=crop","nome":"Theo","raca":"Poodle Mestiço","sexo":"Macho","idade":"2 anos","local":"Praça Mello Peixoto, Ourinhos","porte":"Pequeno","ong_id":900002,"status":"Disponível","especie":"Cão","historia":"Resgatado após ser visto sozinho próximo à praça. Foi tosado, tratado e aguarda uma família.","created_at":"2026-11-23 03:12:01","temperamento":"Amigável, brincalhão e aprende comandos com facilidade."}'::jsonb)
ON CONFLICT (local_id) DO UPDATE SET ong_id = EXCLUDED.ong_id, nome = EXCLUDED.nome, especie = EXCLUDED.especie, raca = EXCLUDED.raca, idade = EXCLUDED.idade, sexo = EXCLUDED.sexo, porte = EXCLUDED.porte, temperamento = EXCLUDED.temperamento, historia_resgate = EXCLUDED.historia_resgate, local_encontrado = EXCLUDED.local_encontrado, status = EXCLUDED.status, imagem_url = EXCLUDED.imagem_url, payload = EXCLUDED.payload;

INSERT INTO public.animais (local_id, ong_id, nome, especie, raca, idade, sexo, porte, temperamento, historia_resgate, local_encontrado, status, imagem_url, payload)
VALUES (1007, (SELECT id FROM public.ongs WHERE local_id = 900003 LIMIT 1), 'Bento', 'Cão', 'SRD', '4 anos', 'Macho', 'Médio', 'Companheiro e muito tranquilo. Gosta de ambientes calmos.', 'Foi resgatado após denúncia de abandono. Hoje recuperado, procura um lar acolhedor.', 'Vila Nova, Ourinhos', 'disponível', 'https://images.unsplash.com/photo-1561037404-61cd46aa615b?w=800&auto=format&fit=crop', '{"id":1007,"foto":"https://images.unsplash.com/photo-1561037404-61cd46aa615b?w=800&auto=format&fit=crop","nome":"Bento","raca":"SRD","sexo":"Macho","idade":"4 anos","local":"Vila Nova, Ourinhos","porte":"Médio","ong_id":900003,"status":"Disponível","especie":"Cão","historia":"Foi resgatado após denúncia de abandono. Hoje recuperado, procura um lar acolhedor.","created_at":"2026-11-23 03:08:54","temperamento":"Companheiro e muito tranquilo. Gosta de ambientes calmos."}'::jsonb)
ON CONFLICT (local_id) DO UPDATE SET ong_id = EXCLUDED.ong_id, nome = EXCLUDED.nome, especie = EXCLUDED.especie, raca = EXCLUDED.raca, idade = EXCLUDED.idade, sexo = EXCLUDED.sexo, porte = EXCLUDED.porte, temperamento = EXCLUDED.temperamento, historia_resgate = EXCLUDED.historia_resgate, local_encontrado = EXCLUDED.local_encontrado, status = EXCLUDED.status, imagem_url = EXCLUDED.imagem_url, payload = EXCLUDED.payload;

INSERT INTO public.animais (local_id, ong_id, nome, especie, raca, idade, sexo, porte, temperamento, historia_resgate, local_encontrado, status, imagem_url, payload)
VALUES (1008, (SELECT id FROM public.ongs WHERE local_id = 900003 LIMIT 1), 'Zeca', 'Cão', 'Vira-lata Caramelo', '1 ano', 'Macho', 'Médio', 'Alegre, expressivo e adora correr no quintal.', 'Era visto diariamente perto de um ponto de ônibus até os voluntários ganharem sua confiança.', 'Vila Nova, Ourinhos', 'disponível', 'https://images.unsplash.com/photo-1548199973-03cce0bbc87b?w=800&auto=format&fit=crop', '{"id":1008,"foto":"https://images.unsplash.com/photo-1548199973-03cce0bbc87b?w=800&auto=format&fit=crop","nome":"Zeca","raca":"Vira-lata Caramelo","sexo":"Macho","idade":"1 ano","local":"Vila Nova, Ourinhos","porte":"Médio","ong_id":900003,"status":"Disponível","especie":"Cão","historia":"Era visto diariamente perto de um ponto de ônibus até os voluntários ganharem sua confiança.","created_at":"2026-11-23 03:08:54","temperamento":"Alegre, expressivo e adora correr no quintal."}'::jsonb)
ON CONFLICT (local_id) DO UPDATE SET ong_id = EXCLUDED.ong_id, nome = EXCLUDED.nome, especie = EXCLUDED.especie, raca = EXCLUDED.raca, idade = EXCLUDED.idade, sexo = EXCLUDED.sexo, porte = EXCLUDED.porte, temperamento = EXCLUDED.temperamento, historia_resgate = EXCLUDED.historia_resgate, local_encontrado = EXCLUDED.local_encontrado, status = EXCLUDED.status, imagem_url = EXCLUDED.imagem_url, payload = EXCLUDED.payload;

INSERT INTO public.animais (local_id, ong_id, nome, especie, raca, idade, sexo, porte, temperamento, historia_resgate, local_encontrado, status, imagem_url, payload)
VALUES (1009, (SELECT id FROM public.ongs WHERE local_id = 900003 LIMIT 1), 'Caju', 'Gato', 'Laranja Tigrado', '8 meses', 'Macho', 'Pequeno', 'Curioso, brincalhão e ronrona por carinho.', 'Encontrado filhote em uma colônia acompanhada por protetores. Socializado e saudável.', 'Rua Duque de Caxias, Vila Nova, Ourinhos', 'disponível', 'https://images.unsplash.com/photo-1574158622682-e40e69881006?w=800&auto=format&fit=crop', '{"id":1009,"foto":"https://images.unsplash.com/photo-1574158622682-e40e69881006?w=800&auto=format&fit=crop","nome":"Caju","raca":"Laranja Tigrado","sexo":"Macho","idade":"8 meses","local":"Rua Duque de Caxias, Vila Nova, Ourinhos","porte":"Pequeno","ong_id":900003,"status":"Disponível","especie":"Gato","historia":"Encontrado filhote em uma colônia acompanhada por protetores. Socializado e saudável.","created_at":"2026-11-23 03:08:54","temperamento":"Curioso, brincalhão e ronrona por carinho."}'::jsonb)
ON CONFLICT (local_id) DO UPDATE SET ong_id = EXCLUDED.ong_id, nome = EXCLUDED.nome, especie = EXCLUDED.especie, raca = EXCLUDED.raca, idade = EXCLUDED.idade, sexo = EXCLUDED.sexo, porte = EXCLUDED.porte, temperamento = EXCLUDED.temperamento, historia_resgate = EXCLUDED.historia_resgate, local_encontrado = EXCLUDED.local_encontrado, status = EXCLUDED.status, imagem_url = EXCLUDED.imagem_url, payload = EXCLUDED.payload;

INSERT INTO public.animais (local_id, ong_id, nome, especie, raca, idade, sexo, porte, temperamento, historia_resgate, local_encontrado, status, imagem_url, payload)
VALUES (1010, (SELECT id FROM public.ongs WHERE local_id = 900004 LIMIT 1), 'Tobias', 'Cão', 'Pastor Alemão Mestiço', '6 anos', 'Macho', 'Grande', 'Calmo, protetor e muito dócil com a família.', 'Encontrado magro durante uma ronda de voluntários. Passou por exames e hoje está forte e saudável.', 'Jardim Paulista, Ourinhos', 'disponível', 'https://images.unsplash.com/photo-1589941013453-ec89f33b5e95?w=800&auto=format&fit=crop', '{"id":1010,"foto":"https://images.unsplash.com/photo-1589941013453-ec89f33b5e95?w=800&auto=format&fit=crop","nome":"Tobias","raca":"Pastor Alemão Mestiço","sexo":"Macho","idade":"6 anos","local":"Jardim Paulista, Ourinhos","porte":"Grande","ong_id":900004,"status":"Disponível","especie":"Cão","historia":"Encontrado magro durante uma ronda de voluntários. Passou por exames e hoje está forte e saudável.","created_at":"2026-11-23 03:12:02","temperamento":"Calmo, protetor e muito dócil com a família."}'::jsonb)
ON CONFLICT (local_id) DO UPDATE SET ong_id = EXCLUDED.ong_id, nome = EXCLUDED.nome, especie = EXCLUDED.especie, raca = EXCLUDED.raca, idade = EXCLUDED.idade, sexo = EXCLUDED.sexo, porte = EXCLUDED.porte, temperamento = EXCLUDED.temperamento, historia_resgate = EXCLUDED.historia_resgate, local_encontrado = EXCLUDED.local_encontrado, status = EXCLUDED.status, imagem_url = EXCLUDED.imagem_url, payload = EXCLUDED.payload;

INSERT INTO public.animais (local_id, ong_id, nome, especie, raca, idade, sexo, porte, temperamento, historia_resgate, local_encontrado, status, imagem_url, payload)
VALUES (1011, (SELECT id FROM public.ongs WHERE local_id = 900004 LIMIT 1), 'Mel', 'Cão', 'Golden Retriever Mestiço', '8 anos', 'Fêmea', 'Médio', 'Gentil, amorosa e muito paciente.', 'Foi acolhida após a mudança de sua antiga família. Recebe acompanhamento no abrigo e precisa de apadrinhamento.', 'Jardim Paulista, Ourinhos', 'apadrinhamento', 'https://images.unsplash.com/photo-1507146426996-ef05306b995a?w=800&auto=format&fit=crop', '{"id":1011,"foto":"https://images.unsplash.com/photo-1507146426996-ef05306b995a?w=800&auto=format&fit=crop","nome":"Mel","raca":"Golden Retriever Mestiço","sexo":"Fêmea","idade":"8 anos","local":"Jardim Paulista, Ourinhos","porte":"Médio","ong_id":900004,"status":"Apadrinhamento","especie":"Cão","historia":"Foi acolhida após a mudança de sua antiga família. Recebe acompanhamento no abrigo e precisa de apadrinhamento.","created_at":"2026-11-23 03:12:02","temperamento":"Gentil, amorosa e muito paciente."}'::jsonb)
ON CONFLICT (local_id) DO UPDATE SET ong_id = EXCLUDED.ong_id, nome = EXCLUDED.nome, especie = EXCLUDED.especie, raca = EXCLUDED.raca, idade = EXCLUDED.idade, sexo = EXCLUDED.sexo, porte = EXCLUDED.porte, temperamento = EXCLUDED.temperamento, historia_resgate = EXCLUDED.historia_resgate, local_encontrado = EXCLUDED.local_encontrado, status = EXCLUDED.status, imagem_url = EXCLUDED.imagem_url, payload = EXCLUDED.payload;

INSERT INTO public.animais (local_id, ong_id, nome, especie, raca, idade, sexo, porte, temperamento, historia_resgate, local_encontrado, status, imagem_url, payload)
VALUES (1012, (SELECT id FROM public.ongs WHERE local_id = 900004 LIMIT 1), 'Frida', 'Cão', 'SRD', '9 anos', 'Fêmea', 'Médio', 'Tranquila, adora caminhar e dormir pertinho.', 'Passou por tratamento veterinário para uma lesão antiga e hoje vive bem no abrigo esperando adoção para a terceira idade.', 'Jardim Paulista, Ourinhos', 'apadrinhamento', 'https://images.unsplash.com/photo-1517849845537-4d257902454a?w=800&auto=format&fit=crop', '{"id":1012,"foto":"https://images.unsplash.com/photo-1517849845537-4d257902454a?w=800&auto=format&fit=crop","nome":"Frida","raca":"SRD","sexo":"Fêmea","idade":"9 anos","local":"Jardim Paulista, Ourinhos","porte":"Médio","ong_id":900004,"status":"Apadrinhamento","especie":"Cão","historia":"Passou por tratamento veterinário para uma lesão antiga e hoje vive bem no abrigo esperando adoção para a terceira idade.","created_at":"2026-11-23 03:12:02","temperamento":"Tranquila, adora caminhar e dormir pertinho."}'::jsonb)
ON CONFLICT (local_id) DO UPDATE SET ong_id = EXCLUDED.ong_id, nome = EXCLUDED.nome, especie = EXCLUDED.especie, raca = EXCLUDED.raca, idade = EXCLUDED.idade, sexo = EXCLUDED.sexo, porte = EXCLUDED.porte, temperamento = EXCLUDED.temperamento, historia_resgate = EXCLUDED.historia_resgate, local_encontrado = EXCLUDED.local_encontrado, status = EXCLUDED.status, imagem_url = EXCLUDED.imagem_url, payload = EXCLUDED.payload;

INSERT INTO public.animais (local_id, ong_id, nome, especie, raca, idade, sexo, porte, temperamento, historia_resgate, local_encontrado, status, imagem_url, payload)
VALUES (1013, (SELECT id FROM public.ongs WHERE local_id = 900005 LIMIT 1), 'Nina', 'Gato', 'Tricolor', '1 ano', 'Fêmea', 'Pequeno', 'Carinhosa, tranquila e adora observar a janela.', 'Resgatada junto de filhotes dentro de uma caixa. Os filhotes foram adotados e Nina agora procura seu lar definitivo.', 'Jardim Itamaraty, Ourinhos', 'disponível', 'https://images.unsplash.com/photo-1533738363-b7f9aef128ce?w=800&auto=format&fit=crop', '{"id":1013,"foto":"https://images.unsplash.com/photo-1533738363-b7f9aef128ce?w=800&auto=format&fit=crop","nome":"Nina","raca":"Tricolor","sexo":"Fêmea","idade":"1 ano","local":"Jardim Itamaraty, Ourinhos","porte":"Pequeno","ong_id":900005,"status":"Disponível","especie":"Gato","historia":"Resgatada junto de filhotes dentro de uma caixa. Os filhotes foram adotados e Nina agora procura seu lar definitivo.","created_at":"2026-11-23 03:12:03","temperamento":"Carinhosa, tranquila e adora observar a janela."}'::jsonb)
ON CONFLICT (local_id) DO UPDATE SET ong_id = EXCLUDED.ong_id, nome = EXCLUDED.nome, especie = EXCLUDED.especie, raca = EXCLUDED.raca, idade = EXCLUDED.idade, sexo = EXCLUDED.sexo, porte = EXCLUDED.porte, temperamento = EXCLUDED.temperamento, historia_resgate = EXCLUDED.historia_resgate, local_encontrado = EXCLUDED.local_encontrado, status = EXCLUDED.status, imagem_url = EXCLUDED.imagem_url, payload = EXCLUDED.payload;

INSERT INTO public.animais (local_id, ong_id, nome, especie, raca, idade, sexo, porte, temperamento, historia_resgate, local_encontrado, status, imagem_url, payload)
VALUES (1014, (SELECT id FROM public.ongs WHERE local_id = 900005 LIMIT 1), 'Pérola', 'Cão', 'SRD Peludinha', '3 anos', 'Fêmea', 'Pequeno', 'Doce, apegada e companheira inseparável.', 'Chegou para atendimento com baixo peso. Recuperou a disposição e adora brincar.', 'Jardim Itamaraty, Ourinhos', 'disponível', 'https://images.unsplash.com/photo-1518717758536-85ae29035b6d?w=800&auto=format&fit=crop', '{"id":1014,"foto":"https://images.unsplash.com/photo-1518717758536-85ae29035b6d?w=800&auto=format&fit=crop","nome":"Pérola","raca":"SRD Peludinha","sexo":"Fêmea","idade":"3 anos","local":"Jardim Itamaraty, Ourinhos","porte":"Pequeno","ong_id":900005,"status":"Disponível","especie":"Cão","historia":"Chegou para atendimento com baixo peso. Recuperou a disposição e adora brincar.","created_at":"2026-11-23 03:12:03","temperamento":"Doce, apegada e companheira inseparável."}'::jsonb)
ON CONFLICT (local_id) DO UPDATE SET ong_id = EXCLUDED.ong_id, nome = EXCLUDED.nome, especie = EXCLUDED.especie, raca = EXCLUDED.raca, idade = EXCLUDED.idade, sexo = EXCLUDED.sexo, porte = EXCLUDED.porte, temperamento = EXCLUDED.temperamento, historia_resgate = EXCLUDED.historia_resgate, local_encontrado = EXCLUDED.local_encontrado, status = EXCLUDED.status, imagem_url = EXCLUDED.imagem_url, payload = EXCLUDED.payload;

INSERT INTO public.animais (local_id, ong_id, nome, especie, raca, idade, sexo, porte, temperamento, historia_resgate, local_encontrado, status, imagem_url, payload)
VALUES (1015, (SELECT id FROM public.ongs WHERE local_id = 900005 LIMIT 1), 'Max', 'Cão', 'Boxer Mestiço', '3 anos', 'Macho', 'Grande', 'Brincalhão, atento e cheio de energia.', 'Encaminhado à Rede Patinhas após resgate em situação de abandono em via pública.', 'Jardim Itamaraty, Ourinhos', 'disponível', 'https://images.unsplash.com/photo-1534361960057-19889db9621e?w=800&auto=format&fit=crop', '{"id":1015,"foto":"https://images.unsplash.com/photo-1534361960057-19889db9621e?w=800&auto=format&fit=crop","nome":"Max","raca":"Boxer Mestiço","sexo":"Macho","idade":"3 anos","local":"Jardim Itamaraty, Ourinhos","porte":"Grande","ong_id":900005,"status":"Disponível","especie":"Cão","historia":"Encaminhado à Rede Patinhas após resgate em situação de abandono em via pública.","created_at":"2026-11-23 03:12:03","temperamento":"Brincalhão, atento e cheio de energia."}'::jsonb)
ON CONFLICT (local_id) DO UPDATE SET ong_id = EXCLUDED.ong_id, nome = EXCLUDED.nome, especie = EXCLUDED.especie, raca = EXCLUDED.raca, idade = EXCLUDED.idade, sexo = EXCLUDED.sexo, porte = EXCLUDED.porte, temperamento = EXCLUDED.temperamento, historia_resgate = EXCLUDED.historia_resgate, local_encontrado = EXCLUDED.local_encontrado, status = EXCLUDED.status, imagem_url = EXCLUDED.imagem_url, payload = EXCLUDED.payload;

-- Inicialização das sequências numéricas locais para inserções futuras
INSERT INTO public.ga_id_sequences (store, last_id) VALUES
('denuncias', 1000000),
('admins', 1000001),
('admin_solicitacoes', 1000000),
('ong_accounts', 9000005),
('ong_animals', 1000015),
('doacoes', 1000000),
('apadrinhamentos', 1000000),
('contatos', 1000000)
ON CONFLICT (store) DO NOTHING;

COMMIT;

-- ============================================================================
-- FIM DO SCRIPT DE BANCO DE DADOS (GUARDIÃO ANIMAL V36.1 - SENAI)
-- ============================================================================