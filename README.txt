CATÁLOGO HE — LINHA LEVE E PESADA 2026/2027

VERSÃO COM GRUPOS + CELULAR + WHATSAPP + PAINEL ADMINISTRATIVO

1) INSTALAÇÃO NO XAMPP
- Instale o XAMPP.
- Copie esta pasta para C:\xampp\htdocs\catalogo_eleds_completo\
- Abra o XAMPP e inicie o Apache.
- Acesse no navegador:
  http://localhost/catalogo_eleds_completo/

2) PAINEL ADMINISTRATIVO
Acesse:
  http://localhost/catalogo_eleds_completo/admin.php

Login padrão:
  Usuário: admin
  Senha: admin123

IMPORTANTE: antes de publicar o site na internet, altere ADMIN_USER e ADMIN_PASSWORD no arquivo admin_config.php.

No painel é possível:
- cadastrar produtos;
- editar produtos;
- excluir produtos;
- alterar código, nome, grupo, marca e aplicação;
- enviar imagem JPG, PNG ou WEBP;
- pesquisar produtos;
- criar usuários adicionais;
- alterar a própria senha;
- excluir outros usuários;

3) GRUPOS
Os grupos são criados automaticamente a partir do campo "category" dos produtos.
Exemplos presentes no catálogo:
- Lâmpadas
- Faróis
- Chaves e Interruptores
- Lanternas
- Chicotes
- Motores de Vidro
- Conexões e Terminais
- Válvulas e Buzinas
- e outros grupos do catálogo.

4) PESQUISA
A busca procura em:
- código;
- nome/descrição;
- grupo/categoria;
- marca;
- aplicação/compatibilidade.

A busca ignora acentos e aceita várias palavras ao mesmo tempo.

5) WHATSAPP
Cada produto possui botão "WhatsApp" e o site também possui botão flutuante no celular.
Número configurado: (87) 98117-7326

Para trocar o número, procure por:
5587981177326
nos arquivos index.php, produto.php e assets/js/app.js.

6) DADOS
Os produtos ficam em:
  data/products.json

As imagens ficam em:
  assets/products/

O painel administrativo atualiza o JSON automaticamente.

7) SEGURANÇA
Este painel foi pensado para uso local/XAMPP. Se for publicar na internet, troque a senha padrão e, de preferência, implemente autenticação mais robusta/HTTPS.

WHATSAPP NO PAINEL ADMINISTRATIVO
-------------------------------
Agora os números de WhatsApp podem ser cadastrados e alterados pelo painel administrativo.
Acesse: http://localhost/catalogo_eleds_completo/admin.php
Depois de entrar, vá até a seção "Números de WhatsApp".

É possível:
- adicionar vários números;
- editar nome, número e forma de exibição;
- escolher qual número será o principal;
- excluir números.

O número principal é usado nos botões "WhatsApp" e "Pedir orçamento" dos produtos.
Os demais números cadastrados aparecem como opções de atendimento na área de WhatsApp do catálogo.


8) CATÁLOGO EM PDF
- O botão "Catálogo em PDF" está disponível no catálogo e no painel administrativo.
- Ele abre uma versão pronta para impressão em A4, com capa e todos os produtos cadastrados.
- O layout usa 9 produtos por página, com imagem, código, descrição, marca e aplicação quando cadastrada.
- Ao abrir, o navegador pode mostrar a janela de impressão. Escolha "Salvar como PDF" para gerar o arquivo.
- O PDF é montado automaticamente a partir do data/products.json; ao cadastrar, editar ou excluir produtos, a próxima geração já usa os dados atualizados.
- O arquivo responsável pela geração é catalogo_pdf.php.

9) USUÁRIOS E SENHAS
- Os usuários ficam em data/users.json.
- O primeiro acesso continua sendo: admin / admin123, caso o users.json ainda não exista.
- As senhas são armazenadas com hash, não em texto puro.
- No painel, em 'Usuários e senha', é possível alterar a própria senha e criar/excluir outros usuários.
- Por enquanto não existem níveis de permissão: todos os usuários cadastrados têm acesso completo ao painel.


10) FERRAMENTAS DO PAINEL (admin_tools.php)
- Excel: exportar todos os produtos (ou só os sem aplicação / sem foto) e importar .xlsx ou .csv.
  Colunas: Código (obrigatória), Nome, Grupo, Marca, Aplicação, Medidas.
  Código existente = atualiza só as células preenchidas; código novo = cria o produto (precisa de nome).
- Duplicar produto: botão "Duplicar" na lista do painel (a cópia nasce sem fotos).
- Histórico: toda alteração (inclusive importações) é registrada em data/history.jsonl.
- Backup automático: antes de cada alteração o products.json é copiado para data/backups/ (60 mais recentes).
  Fotos não entram nesse backup: copie assets/products de vez em quando.
- data/.htaccess bloqueia o acesso direto à pasta data pela internet (Apache).
