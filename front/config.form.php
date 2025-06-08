<?php
include('../../../inc/includes.php');

$plugin = new Plugin();
if (!$plugin->isActivated('custosdeslocamento')) {
   Html::displayNotFoundError();
}

Session::checkRight('config', UPDATE);



$config = new PluginCustosdeslocamentoConfig();

if (isset($_POST['update_config'])) {
    // Update the configuration
    if ($config->updateConfig($_POST)) {
        Session::addMessageAfterRedirect(
            __('Configuração atualizada com sucesso', 'custosdeslocamento'),
            true,
            INFO
        );
    }
    
    Html::redirect(Plugin::getWebDir('custosdeslocamento') . '/front/caixa.php');
}

Html::footer();