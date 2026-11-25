<?php
require __DIR__.'/includes/bootstrap.php';
flash('ok', 'Os perfis dos animais agora ficam dentro das páginas de cada ONG.');
redirect('ongs.php');
