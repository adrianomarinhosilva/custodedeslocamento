<?php
include('../../../inc/includes.php');

$plugin = new Plugin();
if (!$plugin->isActivated('custosdeslocamento')) {
   Html::displayNotFoundError();
}

Session::checkRight('config', UPDATE);



$caixa = new PluginCustosdeslocamentoCaixa();

if (isset($_POST['add_funds'])) {
    // Converter e validar o valor
    $valor = isset($_POST['valor']) ? str_replace(',', '.', $_POST['valor']) : 0;
    $valor = floatval($valor);
    
    $observacao = isset($_POST['observacao']) ? $_POST['observacao'] : '';
    
    if ($valor <= 0) {
        Session::addMessageAfterRedirect(
            __('O valor deve ser maior que zero', 'custosdeslocamento'),
            true,
            ERROR
        );
    } else {
        if ($caixa->addFunds($valor, $observacao)) {
            Session::addMessageAfterRedirect(
                __('Fundos adicionados com sucesso', 'custosdeslocamento'),
                true,
                INFO
            );
        } else {
            Session::addMessageAfterRedirect(
                __('Erro ao adicionar fundos', 'custosdeslocamento'),
                true,
                ERROR
            );
        }
    }
    
    Html::redirect(Plugin::getWebDir('custosdeslocamento') . '/front/caixa.php');
}

Html::redirect(Plugin::getWebDir('custosdeslocamento') . '/front/caixa.php');