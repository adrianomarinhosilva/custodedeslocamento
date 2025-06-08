<?php
include('../../../inc/includes.php');
$plugin = new Plugin();
if (!$plugin->isActivated('custosdeslocamento')) {
   Html::displayNotFoundError();
}

Html::header('Gestão de Caixa', $_SERVER['PHP_SELF'], 'custosdeslocamento', 'custosdeslocamento');

// Título centralizado
echo "<div class='center'>";
echo "</div>";

// Mostrar o saldo em destaque (com fonte reduzida e alinhado à direita)
$saldo = PluginCustosdeslocamentoCaixa::getSaldo();

// Definir estilo baseado no valor do saldo
$estilo_saldo = '';
$mensagem_adicional = '';
if ($saldo < 350) {
    // Estilo vermelho para saldo baixo
    $estilo_saldo = 'background-color: #f8d7da; color: #721c24;';
    $mensagem_adicional = '<span style="margin-left: 10px; font-size: 12px;"> Saldo abaixo de R$ 350,00, acione o financeiro@suaempresa.com.br</span>';
} else {
    // Estilo verde para saldo ok
    $estilo_saldo = 'background-color: #d4edda; color: #155724;';
}

// Bloco de saldo alinhado à direita com fonte menor
echo "<div style='text-align: right; margin-bottom: 20px;'>";
echo "<div style='display: inline-block; $estilo_saldo padding: 10px 20px; border-radius: 5px; font-size: 14px;'>";
echo "<strong>Saldo Atual: R$ " . number_format($saldo, 2, ',', '.') . "</strong>";
echo $mensagem_adicional;
echo "</div>";
echo "</div>";

// Layout em uma única coluna com conteúdo alinhado à direita
echo "<div style='text-align: right; margin-bottom: 20px;'>";

// Formulário para adicionar fundos
echo "<div style='margin-bottom: 20px;'>";
PluginCustosdeslocamentoCaixa::showCaixaForm();
echo "</div>";

// Configurações 
echo "<div style='margin-bottom: 20px;'>";
PluginCustosdeslocamentoCaixa::showConfigForm();
echo "</div>";

echo "</div>";

// Histórico de transações
PluginCustosdeslocamentoCaixa::showTransactionsHistory();

Html::footer();