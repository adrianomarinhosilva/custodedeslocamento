<?php
// Salve como: /var/www/glpi/plugins/custosdeslocamento/front/diagnostico.php

include('../../../inc/includes.php');

$plugin = new Plugin();
if (!$plugin->isActivated('custosdeslocamento')) {
   Html::displayNotFoundError();
}

Session::checkRight('config', READ);

Html::header('Diagnóstico do Plugin', $_SERVER['PHP_SELF'], 'custosdeslocamento', 'custosdeslocamento');

echo "<div style='width: 80%; margin: 0 auto; padding: 20px;'>";
echo "<h1>Diagnóstico do Plugin Custos de Deslocamento</h1>";

// Criar arquivo de log
$log_file = GLPI_LOG_DIR . '/plugin_custosdeslocamento_debug.log';
file_put_contents($log_file, "Início do diagnóstico: " . date('Y-m-d H:i:s') . "\n");

function log_message($message) {
    global $log_file;
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
    echo "<p>LOG: $message</p>";
}

// Testar conexão com o banco de dados
global $DB;
log_message("Testando conexão com o banco de dados...");

if (!$DB) {
    log_message("ERRO: Conexão com o banco de dados não está disponível!");
} else {
    log_message("OK: Conexão com o banco de dados estabelecida.");
}

// Verificar tabelas do plugin
log_message("Verificando tabelas do plugin...");

$tabelas = [
    'glpi_plugin_custosdeslocamento_viagens',
    'glpi_plugin_custosdeslocamento_caixa'
];

foreach ($tabelas as $tabela) {
    if ($DB->tableExists($tabela)) {
        $count = $DB->request("SELECT COUNT(*) as total FROM `$tabela`")->current();
        log_message("OK: Tabela '$tabela' existe e contém {$count['total']} registros.");
    } else {
        log_message("ERRO: Tabela '$tabela' não existe!");
    }
}

// Testar consulta simples
log_message("Testando consulta simples nas tabelas...");

try {
    // Consulta simples na tabela de viagens
    $query_viagens = "SELECT * FROM glpi_plugin_custosdeslocamento_viagens LIMIT 1";
    $result_viagens = $DB->request($query_viagens);
    
    if (count($result_viagens) > 0) {
        $dados = $result_viagens->current();
        log_message("OK: Consulta na tabela de viagens retornou dados. Primeiro registro ID: " . $dados['id']);
        
        echo "<h3>Amostra da tabela de viagens:</h3>";
        echo "<pre>" . print_r($dados, true) . "</pre>";
    } else {
        log_message("AVISO: Consulta na tabela de viagens não retornou dados.");
    }
    
    // Consulta simples na tabela de caixa
    $query_caixa = "SELECT * FROM glpi_plugin_custosdeslocamento_caixa LIMIT 1";
    $result_caixa = $DB->request($query_caixa);
    
    if (count($result_caixa) > 0) {
        $dados = $result_caixa->current();
        log_message("OK: Consulta na tabela de caixa retornou dados. Primeiro registro ID: " . $dados['id']);
        
        echo "<h3>Amostra da tabela de caixa:</h3>";
        echo "<pre>" . print_r($dados, true) . "</pre>";
    } else {
        log_message("AVISO: Consulta na tabela de caixa não retornou dados.");
    }
    
    // Testar a consulta do relatório (simplificada)
    log_message("Testando a consulta do relatório (simplificada)...");
    
    $query_teste = "SELECT 
        v.id, v.ticket_id, v.data_hora, v.tecnico_id, v.custo, v.status,
        c.tipo_operacao, c.saldo_anterior, c.saldo_posterior
    FROM glpi_plugin_custosdeslocamento_viagens v
    LEFT JOIN glpi_plugin_custosdeslocamento_caixa c ON v.id = c.viagem_id
    ORDER BY v.id DESC
    LIMIT 5";
    
    $result_teste = $DB->request($query_teste);
    
    if (count($result_teste) > 0) {
        log_message("OK: Consulta de teste do relatório retornou " . count($result_teste) . " registros.");
        
        echo "<h3>Resultados da consulta de teste:</h3>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr>
                <th>ID Viagem</th>
                <th>Ticket</th>
                <th>Data</th>
                <th>Técnico</th>
                <th>Custo</th>
                <th>Status</th>
                <th>Tipo Operação</th>
                <th>Saldo Anterior</th>
                <th>Saldo Posterior</th>
            </tr>";
        
        foreach ($result_teste as $row) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['ticket_id']}</td>";
            echo "<td>{$row['data_hora']}</td>";
            echo "<td>{$row['tecnico_id']}</td>";
            echo "<td>{$row['custo']}</td>";
            echo "<td>{$row['status']}</td>";
            echo "<td>" . (isset($row['tipo_operacao']) ? $row['tipo_operacao'] : '-') . "</td>";
            echo "<td>" . (isset($row['saldo_anterior']) ? $row['saldo_anterior'] : '-') . "</td>";
            echo "<td>" . (isset($row['saldo_posterior']) ? $row['saldo_posterior'] : '-') . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        log_message("AVISO: Consulta de teste do relatório não retornou dados!");
    }
    
    // Verificar a estrutura da tabela de viagens
    log_message("Verificando a estrutura da tabela de viagens...");
    $colunas_viagens = $DB->request("SHOW COLUMNS FROM glpi_plugin_custosdeslocamento_viagens");
    
    echo "<h3>Estrutura da tabela de viagens:</h3>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Chave</th><th>Padrão</th></tr>";
    
    foreach ($colunas_viagens as $coluna) {
        echo "<tr>";
        echo "<td>{$coluna['Field']}</td>";
        echo "<td>{$coluna['Type']}</td>";
        echo "<td>{$coluna['Null']}</td>";
        echo "<td>{$coluna['Key']}</td>";
        echo "<td>{$coluna['Default']}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Verificar a estrutura da tabela de caixa
    log_message("Verificando a estrutura da tabela de caixa...");
    $colunas_caixa = $DB->request("SHOW COLUMNS FROM glpi_plugin_custosdeslocamento_caixa");
    
    echo "<h3>Estrutura da tabela de caixa:</h3>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Chave</th><th>Padrão</th></tr>";
    
    foreach ($colunas_caixa as $coluna) {
        echo "<tr>";
        echo "<td>{$coluna['Field']}</td>";
        echo "<td>{$coluna['Type']}</td>";
        echo "<td>{$coluna['Null']}</td>";
        echo "<td>{$coluna['Key']}</td>";
        echo "<td>{$coluna['Default']}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
} catch (Exception $e) {
    log_message("ERRO de exceção: " . $e->getMessage());
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

log_message("Diagnóstico concluído. Arquivo de log salvo em: $log_file");
echo "</div>";

Html::footer();
?>