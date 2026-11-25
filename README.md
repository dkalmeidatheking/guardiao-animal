# Guardião Animal — Rede Colaborativa de Proteção Animal

> **Trabalho de Conclusão de Curso (TCC)**  
> Curso Técnico em Desenvolvimento de Sistemas — **SENAI Ourinhos / SP** (2026)  
> **Autores:** Arthur Morais, Davi Kiliam e Vinicius Lourenço  
> **Versão da Aplicação:** V36.1 (Supabase Native Architecture)  
> **Hospedagem em Produção:** [http://guardiaoanimal.fwh.is/](http://guardiaoanimal.fwh.is/)

---

## 📌 Visão Geral do Projeto

O **Guardião Animal** é uma plataforma web desenvolvida para aproximar cidadãos, organizações não governamentais (ONGs) e protetores independentes em **Ourinhos - SP**. A aplicação visa dar transparência e agilidade ao atendimento de ocorrências de animais em situação de risco, facilitando:

1. **Registro e Acompanhamento de Denúncias:** Cidadãos podem registrar denúncias anonimamente ou identificando-se, recebendo um código de protocolo único (`GA-XXXXXXXXXX`) para consulta em tempo real.
2. **Mapa Interativo em Tempo Real:** Visualização geográfica por bairros de Ourinhos (Leaflet.js / OpenStreetMap) com marcadores dinâmicos categorizados por urgência e ocorrências em atendimento.
3. **Portal das ONGs Homologadas:** Área restrita onde as organizações gerenciam apenas os casos formalmente encaminhados para seu abrigo, atualizando status (*Em atendimento*, *Concluída* ou *Recusada*).
4. **Painel Administrativo Central:** Gestão de solicitações de novos administradores, moderação de denúncias, distribuição de ocorrências para as ONGs competentes e auditoria completa de ações.
5. **Adoção Responsável e Doações:** Catálogo de animais resgatados com histórico detalhado e formulários de apadrinhamento e suporte financeiro.

---

## 🛠️ Tecnologias Utilizadas

- **Back-end:** PHP 8.x (Arquitetura RESTful, CSRF protection via HMAC, sessões resilientes)
- **Banco de Dados:** PostgreSQL 15+ hospedado no **Supabase** (Row Level Security - RLS, Stored Procedures, sequenciadores transacionais)
- **Front-end:** HTML5 semântico, CSS3 moderno (design responsivo, paleta neutra com contrastes de acessibilidade) e JavaScript Vanilla
- **Mapeamento:** Leaflet.js integrado com OpenStreetMap e georreferenciamento de bairros de Ourinhos
- **Deploy:** InfinityFree (Ambiente de Produção) e Vercel (Configurado via Serverless PHP)

---

## 🗄️ Estrutura do Banco de Dados (Supabase / PostgreSQL)

O banco de dados foi construído seguindo rigorosos padrões relacionais e de segurança da informação:

- `administradores`: Contas de gestão central da plataforma.
- `admin_solicitacoes`: Fila de solicitações de acesso administrativo com aprovação pelo Admin Principal.
- `ongs`: Cadastro institucional das 5 organizações homologadas com perfis públicos e credenciais do portal.
- `animais`: Cadastro detalhado dos animais resgatados (15 animais homologados, 3 por ONG).
- `denuncias`: Registro de ocorrências com protocolo, geolocalização por bairro e urgência.
- `ong_denuncia_atendimentos`: Relacionamento N:N com histórico de atendimento entre ONGs e denúncias.
- `solicitacoes_adocao`: Fila de solicitações de adotantes para avaliação das ONGs.
- `doacoes`: Registros de intenção de doação financeira e suprimentos.
- `apadrinhamentos`: Adesões de padrinhos para suporte mensal de animais.
- `contatos`: Mensagens enviadas pelo Fale Conosco.
- `auditoria`: Log de rastreabilidade de ações executadas no sistema.
- `ga_id_sequences`: Tabela de sequências numéricas controlada pela stored procedure `ga_next_local_id()`.

O script completo DDL e DML para recriação do banco está localizado em:
`CTDS_2026_02_BD_GuardiaoAnimal_TCC_FINAL.sql`

---

## 🚀 Como Executar Localmente

### Pré-requisitos
- PHP 8.2 ou superior (com extensões `curl`, `mbstring`, `openssl`, `pdo_pgsql`)
- Servidor web local (XAMPP, WampServer ou servidor embutido do PHP)

### Passos

1. **Clonar o Repositório:**
   ```bash
   git clone <URL_DO_REPOSITORIO> guardiao_animal
   cd guardiao_animal
   ```

2. **Configurar as Credenciais do Supabase:**
   - Duplique o arquivo de exemplo:
     ```bash
     cp config/guardiao_supabase_private.example.php config/guardiao_supabase_private.php
     ```
   - Edite `config/guardiao_supabase_private.php` informando a sua chave `secret_key` (service_role) do Supabase.

3. **Iniciar o Servidor PHP:**
   ```bash
   php -S localhost:8000
   ```
   Acesse no navegador: `http://localhost:8000`

---

## 🔐 Credenciais de Demonstração / Homologação

| Perfil | E-mail / Usuário | Senha de Teste |
| :--- | :--- | :--- |
| **Administrador Principal** | `guardiaoanimal@gmail.com` | `123456789101112` |
| **ONG A.P.A.O. (ID 900001)** | `apao.ourinhos@gmail.com` | `Ong@123456` |
| **ONG Patas Urbanas (ID 900002)** | `patasurbanas.ourinhos@gmail.com` | `Ong@123456` |
| **ONG São Francisco (ID 900003)** | `resgate.saofrancisco@gmail.com` | `Ong@123456` |
| **ONG Quatro Patas (ID 900004)** | `amor4patas.ourinhos@gmail.com` | `Ong@123456` |
| **ONG Esperança (ID 900005)** | `abrigoesperanca.ourinhos@gmail.com` | `Ong@123456` |

---

## 📄 Licença e Direitos

Projeto acadêmico desenvolvido exclusivamente para fins de avaliação do Trabalho de Conclusão de Curso (TCC) no SENAI Ourinhos / SP.  
Todos os direitos reservados aos autores (2026).
