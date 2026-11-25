<?php
$dogPhotos = [
  1 => 'https://i.pinimg.com/1200x/fc/5d/43/fc5d43cff736c98c58ac5dd3bc21a692.jpg',
  2 => 'https://i.pinimg.com/1200x/49/14/1d/49141dd1cfc1522f60ecc52f6df15d47.jpg',
  3 => 'https://i.pinimg.com/1200x/42/bc/76/42bc76e4599c9e77bade756a6095ffac.jpg',
  4 => 'https://i.pinimg.com/736x/a7/ee/ce/a7eecef6762996154f8063d30883f565.jpg',
  5 => 'https://i.pinimg.com/736x/3c/5c/a7/3c5ca70f663eec06479d9e0f949fbf97.jpg',
  6 => 'https://i.pinimg.com/736x/3d/da/9f/3dda9f1f305bfb588075c93fb1ea9046.jpg',
];
$catPhotos = [
  1 => 'https://i.pinimg.com/736x/3d/e6/60/3de660b7c1ec8b26fe123ae4cd46d2d2.jpg',
  2 => 'https://i.pinimg.com/736x/80/51/76/8051766e2d6511edf0bc839621475281.jpg',
  3 => 'https://i.pinimg.com/1200x/5a/e3/e5/5ae3e5c04c27345a5ea891f15ffbfaac.jpg',
  4 => 'https://i.pinimg.com/1200x/67/94/6f/67946ff9e20495b10d6e2a416432f4b2.jpg',
  5 => 'https://i.pinimg.com/1200x/0a/91/f8/0a91f8956e7743f9c473ca7760515399.jpg',
];


$ongs = [
  1 => ['nome'=>'ONG SOS Cidadania Animal','cidade'=>'Ourinhos, SP','bairro'=>'Centro','ano'=>2016,'foco'=>'Resgate e proteção animal','voluntarios'=>24,'animais'=>52,'descricao'=>'Organização de Ourinhos voltada ao resgate, acolhimento e encaminhamento responsável de cães e gatos em situação de vulnerabilidade.','historia'=>'A SOS Cidadania Animal atua em Ourinhos com rede de voluntários, apoio veterinário e campanhas de conscientização sobre proteção e adoção responsável.','foto'=>'https://ongsoscidadaniaanimal.org/images/site/logo-sobre-a-ong.png','contato'=>'(14) 99812-4401','email'=>'contato@soscidadaniaanimal.org'],
  2 => ['nome'=>'ONG Azul','cidade'=>'Ourinhos, SP','bairro'=>'Jardim Matilde','ano'=>2018,'foco'=>'Adoção responsável','voluntarios'=>19,'animais'=>37,'descricao'=>'Grupo de Ourinhos que aproxima animais resgatados de novas famílias por meio de divulgação, triagem e acompanhamento pós-adoção.','historia'=>'A ONG Azul reúne protetores de Ourinhos para dar visibilidade a animais resgatados, apoiar lares temporários e incentivar adoções conscientes.','foto'=>'https://storage.googleapis.com/atados-v3/user-uploaded/images/e7ce1db4-e764-4bee-b8a6-e1146e612cf1.png','contato'=>'(14) 99703-1820','email'=>'contato@ongazul.org.br'],
  3 => ['nome'=>'Amigos de Ourinhos','cidade'=>'Ourinhos, SP','bairro'=>'Vila Nova','ano'=>2017,'foco'=>'Acolhimento e castração','voluntarios'=>17,'animais'=>29,'descricao'=>'Iniciativa local de Ourinhos com foco em acolhimento temporário, castração e encaminhamento de casos urbanos.','historia'=>'Nasceu da união de protetores independentes da cidade e hoje atua em Ourinhos com lares temporários, orientação à comunidade e apoio em resgates.','foto'=>'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRhn4qjfQyXqdb1y8W6eepjsbHML704ZF7nDIzrjTUwGg0hI7CJOmAulyBM&s=10','contato'=>'(14) 99884-3012','email'=>'amigos@ourinhos.org'],
  4 => ['nome'=>'Clube Amigo dos Animais','cidade'=>'Ourinhos, SP','bairro'=>'Jardim Paulista','ano'=>2014,'foco'=>'Atendimento e reabilitação','voluntarios'=>21,'animais'=>41,'descricao'=>'ONG de Ourinhos dedicada ao acolhimento de animais em recuperação, com divulgação de casos e incentivo à adoção responsável.','historia'=>'O Clube Amigo dos Animais consolidou em Ourinhos uma rotina de atendimento, reabilitação e encaminhamento de animais feridos ou abandonados.','foto'=>'https://static.wixstatic.com/media/a451c1_cffd930cae8c4c1394a0d40ed9374f2f~mv2.jpg','contato'=>'(14) 99610-2278','email'=>'contato@clubeamigodosanimais.org'],
  5 => ['nome'=>'Rede Patinhas de Ourinhos','cidade'=>'Ourinhos, SP','bairro'=>'Jardim Itamaraty','ano'=>2019,'foco'=>'Feiras e adoção','voluntarios'=>15,'animais'=>33,'descricao'=>'Rede de protetores de Ourinhos que divulga animais resgatados, organiza feiras e fortalece o contato entre cuidadores e adotantes.','historia'=>'A Rede Patinhas de Ourinhos trabalha com divulgação digital, apoio a resgates e eventos de adoção na cidade.','foto'=>'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSAb3JMrJHt1WUDKcJF8wSkbLAo4qGXMUYbPwcBMWbutRfx5rSQ8FYKTdc&s=10','contato'=>'(14) 99144-8900','email'=>'redepatinhas@ourinhos.org'],
];
$animals = [
  1=>['nome'=>'Joca','especie'=>'Cão','sexo'=>'Macho','idade'=>'3 anos','porte'=>'Médio','ong'=>1,'local'=>'Rua dos Ipês, Vila Nova, Ourinhos','data'=>'12/08/2026','status'=>'Disponível','temperamento'=>'Carinhoso, brincalhão e se dá bem com outros cães.','historia'=>'Joca apareceu perto de um mercado em uma manhã de chuva. Estava com fome e tinha uma pequena lesão na pata. Depois do atendimento, foi para um lar temporário e recuperou peso.','foto'=>$dogPhotos[1]],
  2=>['nome'=>'Nina','especie'=>'Gato','sexo'=>'Fêmea','idade'=>'1 ano','porte'=>'Pequeno','ong'=>5,'local'=>'Jardim Itamaraty, Ourinhos','data'=>'28/07/2026','status'=>'Disponível','temperamento'=>'Mais reservada no começo, mas fica muito carinhosa depois que pega confiança.','historia'=>'Foi encontrada junto de dois filhotes dentro de uma caixa de papelão. Os filhotes foram adotados e Nina ficou para completar a recuperação.','foto'=>$catPhotos[1]],
  3=>['nome'=>'Tobias','especie'=>'Cão','sexo'=>'Macho','idade'=>'7 anos','porte'=>'Grande','ong'=>4,'local'=>'Jardim Paulista, Ourinhos','data'=>'04/08/2026','status'=>'Disponível','temperamento'=>'Calmo, dócil e gosta de ficar perto de pessoas.','historia'=>'Tobias foi encontrado magro e cansado durante uma ronda de voluntários. Precisou de exames e alguns dias de observação antes de seguir para o abrigo.','foto'=>$dogPhotos[2]],
  4=>['nome'=>'Pipoca','especie'=>'Cão','sexo'=>'Fêmea','idade'=>'8 meses','porte'=>'Pequeno','ong'=>2,'local'=>'Jardim Matilde, Ourinhos','data'=>'14/08/2026','status'=>'Disponível','temperamento'=>'Muito ativa, curiosa e apaixonada por bolinhas.','historia'=>'Chegou com mais dois filhotes depois de uma ninhada ser encontrada perto de um ponto de descarte. Já tomou a primeira dose de vacina.','foto'=>$dogPhotos[3]],
  5=>['nome'=>'Bento','especie'=>'Cão','sexo'=>'Macho','idade'=>'5 anos','porte'=>'Médio','ong'=>3,'local'=>'Vila Nova, Ourinhos','data'=>'19/07/2026','status'=>'Disponível','temperamento'=>'Companheiro e tranquilo. Precisa de uma família com rotina mais calma.','historia'=>'Foi resgatado após uma denúncia de negligência. Chegou assustado, mas respondeu bem ao tratamento e hoje procura uma casa com espaço para ele.','foto'=>$dogPhotos[4]],
  6=>['nome'=>'Luna','especie'=>'Gato','sexo'=>'Fêmea','idade'=>'2 anos','porte'=>'Pequeno','ong'=>2,'local'=>'Jardim Matilde, Ourinhos','data'=>'03/08/2026','status'=>'Disponível','temperamento'=>'Sociável, ronronenta e tranquila com visitas.','historia'=>'Luna apareceu na garagem de uma moradora e ficou ali por alguns dias. Como ninguém apareceu procurando por ela, foi encaminhada para adoção.','foto'=>$catPhotos[2]],
  7=>['nome'=>'Zeca','especie'=>'Cão','sexo'=>'Macho','idade'=>'1 ano','porte'=>'Médio','ong'=>3,'local'=>'Centro, Ourinhos','data'=>'10/08/2026','status'=>'Disponível','temperamento'=>'Alegre e muito ligado a pessoas.','historia'=>'Era visto diariamente perto de um ponto de ônibus. Voluntários conseguiram ganhar sua confiança e levá-lo para avaliação veterinária.','foto'=>$dogPhotos[5]],
  8=>['nome'=>'Mel','especie'=>'Cão','sexo'=>'Fêmea','idade'=>'9 anos','porte'=>'Médio','ong'=>4,'local'=>'Jardim Paulista, Ourinhos','data'=>'21/06/2026','status'=>'Apadrinhamento','temperamento'=>'Gentil e tranquila. Adora dormir perto de alguém.','historia'=>'Foi encontrada após uma mudança de casa. Por ser mais velha, permanece em acolhimento enquanto recebe acompanhamento e espera uma família com calma.','foto'=>$dogPhotos[6]],
  9=>['nome'=>'Fred','especie'=>'Gato','sexo'=>'Macho','idade'=>'4 anos','porte'=>'Pequeno','ong'=>5,'local'=>'Jardim Itamaraty, Ourinhos','data'=>'30/07/2026','status'=>'Disponível','temperamento'=>'Independente, curioso e carinhoso no próprio tempo.','historia'=>'Foi encontrado com uma coleira muito apertada. Após avaliação e cuidados, ficou bem e aguarda adoção.','foto'=>$catPhotos[3]],
  10=>['nome'=>'Babi','especie'=>'Cão','sexo'=>'Fêmea','idade'=>'4 meses','porte'=>'Pequeno','ong'=>2,'local'=>'Jardim Matilde, Ourinhos','data'=>'02/08/2026','status'=>'Disponível','temperamento'=>'Filhote curiosa, brincalhona e muito apegada.','historia'=>'Foi encontrada em uma caixa com uma manta. A equipe cuidou da primeira consulta e iniciou o calendário de vacinação.','foto'=>$dogPhotos[2]],
  11=>['nome'=>'Thor','especie'=>'Cão','sexo'=>'Macho','idade'=>'6 anos','porte'=>'Grande','ong'=>1,'local'=>'Centro, Ourinhos','data'=>'07/08/2026','status'=>'Disponível','temperamento'=>'Forte, dócil e acostumado com pessoas.','historia'=>'Foi retirado de uma situação de risco próxima à avenida. Precisou de consulta e passou por tratamento antes de ser liberado para adoção.','foto'=>$dogPhotos[3]],
  12=>['nome'=>'Amora','especie'=>'Gato','sexo'=>'Fêmea','idade'=>'10 meses','porte'=>'Pequeno','ong'=>5,'local'=>'Jardim Itamaraty, Ourinhos','data'=>'16/08/2026','status'=>'Disponível','temperamento'=>'Doce, ativa e gosta de colo.','historia'=>'Foi resgatada com uma infecção ocular leve e respondeu bem ao tratamento. Hoje está pronta para uma família.','foto'=>$catPhotos[4]],
  13=>['nome'=>'Dudu','especie'=>'Cão','sexo'=>'Macho','idade'=>'2 anos','porte'=>'Médio','ong'=>1,'local'=>'Jardim Itamaraty, Ourinhos','data'=>'09/08/2026','status'=>'Disponível','temperamento'=>'Elétrico na rua, carinhoso em casa e muito inteligente.','historia'=>'Dudu foi encontrado em frente a uma escola. Depois de alguns dias sem identificação, foi levado para o lar temporário.','foto'=>$dogPhotos[4]],
  14=>['nome'=>'Caju','especie'=>'Gato','sexo'=>'Macho','idade'=>'6 meses','porte'=>'Pequeno','ong'=>3,'local'=>'Vila Nova, Ourinhos','data'=>'18/08/2026','status'=>'Disponível','temperamento'=>'Brincalhão e curioso.','historia'=>'Foi encontrado em uma colônia acompanhada por protetores e encaminhado para socialização e adoção.','foto'=>$catPhotos[5]],
  15=>['nome'=>'Frida','especie'=>'Cão','sexo'=>'Fêmea','idade'=>'11 anos','porte'=>'Médio','ong'=>4,'local'=>'Jardim Paulista, Ourinhos','data'=>'11/06/2026','status'=>'Apadrinhamento','temperamento'=>'Muito calma e gosta de companhia.','historia'=>'Frida passou boa parte da vida com uma família que não conseguiu mais cuidar dela. O abrigo acolheu e hoje busca uma adoção para terceira idade.','foto'=>$dogPhotos[5]],
  16=>['nome'=>'Pérola','especie'=>'Cão','sexo'=>'Fêmea','idade'=>'3 anos','porte'=>'Pequeno','ong'=>5,'local'=>'Centro, Ourinhos','data'=>'13/08/2026','status'=>'Disponível','temperamento'=>'Doce e muito ligada a pessoas.','historia'=>'Chegou para atendimento com anemia e baixo peso. Após o tratamento, ganhou disposição e agora procura uma casa tranquila.','foto'=>$dogPhotos[6]],
  17=>['nome'=>'Theo','especie'=>'Cão','sexo'=>'Macho','idade'=>'2 anos','porte'=>'Pequeno','ong'=>2,'local'=>'Jardim Matilde, Ourinhos','data'=>'18/08/2026','status'=>'Disponível','temperamento'=>'Amigável, brincalhão e aprende comandos com facilidade.','historia'=>'Theo foi resgatado após ser visto sozinho próximo a uma praça. Passou por avaliação veterinária e está recebendo acompanhamento da ONG.','foto'=>$dogPhotos[1]],
  18=>['nome'=>'Chico','especie'=>'Cão','sexo'=>'Macho','idade'=>'4 anos','porte'=>'Médio','ong'=>3,'local'=>'Vila Nova, Ourinhos','data'=>'20/08/2026','status'=>'Disponível','temperamento'=>'Tranquilo, companheiro e gosta de caminhadas curtas.','historia'=>'Chico chegou ao lar temporário depois de um resgate comunitário. É acompanhado pelos voluntários e tem perfil próprio na página da organização.','foto'=>$dogPhotos[2]],
  19=>['nome'=>'Max','especie'=>'Cão','sexo'=>'Macho','idade'=>'3 anos','porte'=>'Grande','ong'=>5,'local'=>'Jardim Itamaraty, Ourinhos','data'=>'22/08/2026','status'=>'Disponível','temperamento'=>'Dócil, atento e gosta de brincar ao ar livre.','historia'=>'Max foi encaminhado à Rede Patinhas após ser encontrado em situação de abandono. Agora recebe alimentação e cuidados regulares.','foto'=>$dogPhotos[4]],
  20=>['nome'=>'Lola','especie'=>'Cão','sexo'=>'Fêmea','idade'=>'1 ano','porte'=>'Pequeno','ong'=>5,'local'=>'Jardim Itamaraty, Ourinhos','data'=>'23/08/2026','status'=>'Disponível','temperamento'=>'Carinhosa, curiosa e muito sociável.','historia'=>'Lola foi acolhida por voluntários locais e está em acompanhamento enquanto a ONG organiza os próximos passos do cuidado.','foto'=>$dogPhotos[5]],
];
$news = [
  [
    'titulo'=>'Fez uma denúncia? Entenda os próximos passos', 'categoria'=>'Orientação', 'tempo'=>'3 min de leitura',
    'resumo'=>'Saiba quais informações ajudam a registrar um caso e como ele pode ser encaminhado para atendimento.',
    'foto'=>$dogPhotos[1],
    'intro'=>'Uma ocorrência bem descrita ajuda quem atende a compreender a situação antes de agir. No Guardião Animal, o registro organiza informações e apoia o encaminhamento para uma ONG cadastrada.',
    'secoes'=>[
      ['titulo'=>'1. Registre o local com clareza','texto'=>'Anote o bairro, a rua e um ponto de referência. Descreva onde o animal está e se ele continua no local. Quanto mais precisas as informações, mais fácil é avaliar o relato.'],
      ['titulo'=>'2. Conte o que aconteceu','texto'=>'Informe se há ferimentos visíveis, risco no trânsito, falta de água ou alimento, e há quanto tempo observou a situação. Evite expor dados de terceiros que não sejam necessários.'],
      ['titulo'=>'3. Acompanhe o encaminhamento','texto'=>'A denúncia aparece automaticamente para todas as ONGs com acesso aprovado, sem encaminhamento manual. Cada ONG acompanha sua própria resposta ao caso.'],
    ],
    'destaque'=>'Em situação de perigo imediato, procure também os serviços públicos de emergência ou os órgãos competentes. O formulário do projeto não substitui um atendimento emergencial.'
  ],
  [
    'titulo'=>'Como se preparar para uma adoção responsável', 'categoria'=>'Adoção', 'tempo'=>'4 min de leitura',
    'resumo'=>'Um novo companheiro muda a rotina da casa. Veja o que considerar antes de preencher um formulário de interesse.',
    'foto'=>$catPhotos[2],
    'intro'=>'Adotar é uma decisão que envolve tempo, estrutura e compromisso. Conhecer o temperamento do animal e conversar com quem faz o acolhimento pode evitar frustrações para todos.',
    'secoes'=>[
      ['titulo'=>'1. Pense na rotina da família','texto'=>'Considere tempo disponível, viagens, horários de trabalho, outros animais e espaço para descanso, alimentação e brincadeiras.'],
      ['titulo'=>'2. Avalie os cuidados necessários','texto'=>'Reserve orçamento para alimentação, vacinas, castração, consultas e imprevistos veterinários. Animais filhotes, idosos ou em recuperação podem exigir cuidados extras.'],
      ['titulo'=>'3. Converse com a ONG responsável','texto'=>'Leia o perfil do animal e envie seu interesse. A organização pode orientar sobre o comportamento, necessidades e próximas etapas da adoção.'],
    ],
    'destaque'=>'Fotos são ilustrativas no projeto demonstrativo. Confirme a identidade e as informações de cada animal diretamente com a organização responsável.'
  ],
  [
    'titulo'=>'Apadrinhamento: ajude mesmo sem adotar', 'categoria'=>'Comunidade', 'tempo'=>'3 min de leitura',
    'resumo'=>'Conheça formas de apoiar animais em tratamento e redes de proteção quando adotar ainda não é uma opção.',
    'foto'=>$dogPhotos[6],
    'intro'=>'Nem toda pessoa consegue receber um animal em casa, mas há outras formas de participar. O apoio à rede pode ajudar a manter o cuidado até a recuperação ou a adoção.',
    'secoes'=>[
      ['titulo'=>'Apoie necessidades específicas','texto'=>'Ração, materiais de limpeza, transporte e despesas veterinárias podem fazer diferença. Combine sempre as necessidades com uma organização responsável.'],
      ['titulo'=>'Compartilhe perfis com responsabilidade','texto'=>'Divulgue perfis publicados pela ONG, sem alterar informações sobre o animal. Uma indicação para a família certa pode ser valiosa.'],
      ['titulo'=>'Ofereça seu tempo','texto'=>'Algumas iniciativas recebem apoio para eventos, organização de doações e comunicação. Entre em contato com a rede para saber quais atividades estão disponíveis.'],
    ],
    'destaque'=>'Antes de transferir dinheiro, confirme diretamente os dados da instituição. O Pix exibido neste projeto é demonstrativo.'
  ],
];
