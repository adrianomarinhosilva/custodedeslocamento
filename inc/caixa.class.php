<?php

if (!defined('GLPI_ROOT')) {
   die("Direct access to this file is not allowed.");
}

/**
 * Classe responsável pelo gerenciamento de caixa para deslocamentos
 */
class PluginCustosdeslocamentoCaixa extends CommonDBTM {
    
    static $rightname = 'plugin_custosdeslocamento_caixa';
    
    /**
     * Retorna o nome do tipo
     * 
     * @param int $nb Número para pluralização
     * @return string Nome traduzido
     */
    static function getTypeName($nb = 0) {
        return _n('Caixa', 'Caixas', $nb, 'custosdeslocamento');
    }
    
/**
 * Adiciona fundos ao caixa
 * 
 * @param float $valor O valor a ser adicionado
 * @param string $observacao Observação opcional
 * @return bool|int Novo ID em caso de sucesso, false em caso de falha
 */
function addFunds($valor, $observacao = '') {
    global $DB;
    
    // Garantir que o valor seja um número válido e convertido para float
    $valor = floatval(str_replace(',', '.', $valor));
    
    if ($valor <= 0) {
        Session::addMessageAfterRedirect(
            __('O valor deve ser maior que zero.', 'custosdeslocamento'),
            true,
            ERROR
        );
        return false;
    }
    
    // Obter o saldo atual
    $result = $DB->request([
        'SELECT' => ['saldo_posterior'],
        'FROM'   => 'glpi_plugin_custosdeslocamento_caixa',
        'ORDER'  => ['id DESC'],
        'LIMIT'  => 1
    ]);
    
    $saldo_anterior = 0;
    if (count($result) > 0) {
        foreach ($result as $data) {
            $saldo_anterior = floatval($data['saldo_posterior']);
            break;
        }
    }
    
    $saldo_posterior = $saldo_anterior + $valor;
    
    // Inserir no banco de dados
    $success = $DB->insert(
        'glpi_plugin_custosdeslocamento_caixa',
        [
            'tipo_operacao'   => 'Entrada',
            'ticket_id'       => 0,
            'viagem_id'       => 0,
            'valor'           => $valor,
            'saldo_anterior'  => $saldo_anterior,
            'saldo_posterior' => $saldo_posterior,
            'usuario_id'      => Session::getLoginUserID(),
            'data_hora'       => date('Y-m-d H:i:s'),
            'observacao'      => $observacao,
            'date_creation'   => date('Y-m-d H:i:s'),
            'date_mod'        => date('Y-m-d H:i:s')
        ]
    );
    
    if ($success) {
        return $DB->insertId();
    }
    
    return false;
}
    
    /**
     * Obtém o saldo atual
     * 
     * @return float O saldo atual
     */
    static function getSaldo() {
        global $DB;
        
        $result = $DB->request([
            'SELECT' => ['saldo_posterior'],
            'FROM'   => 'glpi_plugin_custosdeslocamento_caixa',
            'ORDER'  => ['id DESC'],
            'LIMIT'  => 1
        ]);
        
        if (count($result) > 0) {
            foreach ($result as $data) {
                return floatval($data['saldo_posterior']);
            }
        }
        
        return 0;
    }
    
   /**
 * Exibe o formulário de gerenciamento do caixa
 */
static function showCaixaForm() {
    global $CFG_GLPI;
    
    // Default: current month
    $month = date('m');
    $year = date('Y');
    
    if (isset($_GET['month']) && isset($_GET['year'])) {
        $month = $_GET['month'];
        $year = $_GET['year'];
    }
    
    // Dicionário para os nomes dos meses em português
    $meses_pt = [
        1 => 'Janeiro',
        2 => 'Fevereiro',
        3 => 'Março',
        4 => 'Abril',
        5 => 'Maio',
        6 => 'Junho',
        7 => 'Julho',
        8 => 'Agosto',
        9 => 'Setembro',
        10 => 'Outubro',
        11 => 'Novembro',
        12 => 'Dezembro'
    ];
    
    // Acordeão para Adicionar Fundos - Ocupando toda a largura da tela
echo "<div class='card-header' style='background-color: #78c2ad; color: white; cursor: pointer; padding: 2px 8px; height: 30px; display: flex; align-items: center;' data-bs-toggle='collapse' data-bs-target='#collapseAddFunds' aria-expanded='false' aria-controls='collapseAddFunds'>";

echo "<h5 class='m-1'><i class='fas fa-plus-circle me-2'></i>" . __('Adicionar Fundos ao Caixa', 'custosdeslocamento') . "</h5>";
echo "</div>";

    
    echo "<div class='collapse' id='collapseAddFunds'>";
    echo "<div class='card-body' style='background-color: #f8f9fa;'>";
    
    // Interface para adicionar fundos - estilo de linha única
    echo "<form method='post' action='" . Plugin::getWebDir('custosdeslocamento') . "/front/caixa.form.php' class='d-flex flex-wrap align-items-center'>";
    
    // Valor a adicionar
    echo "<div class='input-group me-3' style='width: auto;'>";
    echo "<span class='input-group-text' style='background-color: #e9ecef;'>R$</span>";
    echo "<input type='number' step='0.01' name='valor' class='form-control' placeholder='0,00' required style='width: 150px;'>";
    echo "</div>";
    
    // Observação
    echo "<div class='me-3 flex-grow-1'>";
    echo "<input type='text' name='observacao' class='form-control' placeholder='" . __('Observação (opcional)', 'custosdeslocamento') . "'>";
    echo "</div>";
    
    // Botão adicionar
    echo "<button type='submit' name='add_funds' class='btn btn-success' style='background-color: #78c2ad; border-color: #78c2ad;'>";
    echo "<i class='fas fa-plus-circle'></i>&nbsp;" . __('Adicionar', 'custosdeslocamento');
    echo "</button>";
    
    Html::closeForm();
    
    // Separador
    echo "<hr class='my-3'>";
    
    // Filtro por período e tipo
echo "<div class='d-flex flex-wrap align-items-center'>";

// Título do filtro
echo "<div class='me-3'>";
echo "<h6 class='mb-0'><i class='fas fa-filter'></i>&nbsp;" . __('Filtrar por Período', 'custosdeslocamento') . "</h6>";
echo "</div>";

echo "<form method='get' action='' class='d-flex flex-wrap align-items-center'>";

// Mês
echo "<div class='input-group input-group-sm me-3' style='width: auto;'>";
echo "<span class='input-group-text' style='background-color: #e9ecef;'>" . __('Mês', 'custosdeslocamento') . "</span>";
echo "<select name='month' class='form-select form-select-sm' style='width: auto;'>";
for ($m = 1; $m <= 12; $m++) {
    $selected = ($m == $month) ? 'selected' : '';
    echo "<option value='$m' $selected>" . $meses_pt[$m] . "</option>";
}
echo "</select>";
echo "</div>";

// Ano
echo "<div class='input-group input-group-sm me-3' style='width: auto;'>";
echo "<span class='input-group-text' style='background-color: #e9ecef;'>" . __('Ano', 'custosdeslocamento') . "</span>";
echo "<select name='year' class='form-select form-select-sm' style='width: auto;'>";
$current_year = date('Y');
for ($y = $current_year - 2; $y <= $current_year; $y++) {
    $selected = ($y == $year) ? 'selected' : '';
    echo "<option value='$y' $selected>$y</option>";
}
echo "</select>";
echo "</div>";

// Filtro por tipo de operação (Entrada/Despesa)
$tipo_operacao = '';
if (isset($_GET['tipo_operacao'])) {
    $tipo_operacao = $_GET['tipo_operacao'];
}

echo "<div class='input-group input-group-sm me-3' style='width: auto;'>";
echo "<span class='input-group-text' style='background-color: #e9ecef;'>" . __('Tipo', 'custosdeslocamento') . "</span>";
echo "<select name='tipo_operacao' class='form-select form-select-sm' style='width: auto;'>";
echo "<option value=''>" . __('Todos', 'custosdeslocamento') . "</option>";
echo "<option value='Entrada'" . ($tipo_operacao == 'Entrada' ? ' selected' : '') . ">" . __('Entradas', 'custosdeslocamento') . "</option>";
echo "<option value='Despesa'" . ($tipo_operacao == 'Despesa' ? ' selected' : '') . ">" . __('Despesas', 'custosdeslocamento') . "</option>";
echo "</select>";
echo "</div>";

// Filtro de itens por página
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 5; // Valor padrão: 25 itens
echo "<div class='input-group input-group-sm me-3' style='width: auto;'>";
echo "<span class='input-group-text' style='background-color: #e9ecef;'>" . __('Itens por página', 'custosdeslocamento') . "</span>";
echo "<select name='limit' class='form-select form-select-sm' style='width: auto;'>";
$limit_options = [5, 10, 25, 50, 100, 1000];
foreach ($limit_options as $option) {
    $selected = ($option == $limit) ? 'selected' : '';
    echo "<option value='$option' $selected>$option</option>";
}
echo "</select>";
echo "</div>";

// Botão filtrar
echo "<button type='submit' class='btn btn-sm btn-primary' style='background-color: #6cc3d5; border-color: #6cc3d5;'>";
echo "<i class='fas fa-search'></i>&nbsp;" . __('Filtrar', 'custosdeslocamento');
echo "</button>";

echo "</form>";
echo "</div>"; // fecha div do filtro

echo "</div>"; // Fecha card-body
echo "</div>"; // Fecha collapse
echo "</div>"; // Fecha card
    
    // Adiciona o modal para exibir comentários
    echo "<div class='modal fade' id='comentarioModal' tabindex='-1' aria-labelledby='comentarioModalLabel' aria-hidden='true'>";
    echo "<div class='modal-dialog'>";
    echo "<div class='modal-content'>";
    echo "<div class='modal-header'>";
    echo "<h5 class='modal-title' id='comentarioModalLabel'>" . __('Observação', 'custosdeslocamento') . "</h5>";
    echo "<button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Fechar'></button>";
    echo "</div>";
    echo "<div class='modal-body' id='comentarioConteudo'></div>";
    echo "<div class='modal-footer'>";
    echo "<button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>" . __('Fechar', 'custosdeslocamento') . "</button>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    
    // Modal para exibir comprovantes
    echo "<div class='modal fade' id='comprovanteModal' tabindex='-1' aria-labelledby='comprovanteModalLabel' aria-hidden='true'>";
    echo "<div class='modal-dialog modal-lg'>";
    echo "<div class='modal-content'>";
    echo "<div class='modal-header'>";
    echo "<h5 class='modal-title' id='comprovanteModalLabel'>" . __('Comprovantes', 'custosdeslocamento') . "</h5>";
    echo "<button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Fechar'></button>";
    echo "</div>";
    echo "<div class='modal-body' id='comprovanteConteudo' style='text-align: center;'></div>";
    echo "<div class='modal-footer'>";
    echo "<button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>" . __('Fechar', 'custosdeslocamento') . "</button>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    
    // Script para lidar com os modals
    echo "<script>
        function mostrarComentario(comentario) {
            document.getElementById('comentarioConteudo').innerText = comentario;
            var modal = new bootstrap.Modal(document.getElementById('comentarioModal'));
            modal.show();
        }
        
        function mostrarComprovante(viagemId) {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', '" . $CFG_GLPI['root_doc'] . "/plugins/custosdeslocamento/ajax/getComprovantes.php?viagem_id=' + viagemId, true);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    document.getElementById('comprovanteConteudo').innerHTML = xhr.responseText;
                    var modal = new bootstrap.Modal(document.getElementById('comprovanteModal'));
                    modal.show();
                }
            };
            xhr.send();
        }
        
        // Inicializa com a seção de adicionar fundos fechada por padrão
        document.addEventListener('DOMContentLoaded', function() {
            var collapseElement = document.getElementById('collapseAddFunds');
            if (collapseElement) {
                var bsCollapse = new bootstrap.Collapse(collapseElement, {
                    toggle: true
                });
            }
        });
    </script>";
}
    
    /**
     * Exibe o formulário de configuração do caixa
     */
    static function showConfigForm() {
        global $DB, $CFG_GLPI;
        
        $config = new PluginCustosdeslocamentoConfig();
        $config->getFromDB(1);
        
        echo "<form method='post' action='" . Plugin::getWebDir('custosdeslocamento') . "/front/config.form.php' class='row g-2 mb-3 d-none'>";
        
        echo "<div class='col-md-10'>";
        echo "<div class='input-group'>";
        echo "<span class='input-group-text'>" . __('Valor Mínimo para Caixa (R$)', 'custosdeslocamento') . "</span>";
        echo "<input type='number' step='0.01' name='valor_minimo_alerta' class='form-control' value='" . ($config->fields['valor_minimo_alerta'] ?? 100) . "'>";
        echo "</div>";
        echo "</div>";
        
        echo "<div class='col-md-2'>";
        echo "<button type='submit' name='update_config' class='btn btn-primary w-100'>";
        echo "<i class='fas fa-save'></i>&nbsp;" . __('Salvar', 'custosdeslocamento');
        echo "</button>";
        echo "</div>";
        
        Html::closeForm();
    }
    
/**
 * Exibe o histórico de transações do caixa
 */
static function showTransactionsHistory() {
    global $DB, $CFG_GLPI;
    
    // Default: current month
    $month = date('m');
    $year = date('Y');
    $tipo_operacao = '';

    if (isset($_GET['month']) && isset($_GET['year'])) {
        $month = $_GET['month'];
        $year = $_GET['year'];
    }

    if (isset($_GET['tipo_operacao']) && !empty($_GET['tipo_operacao'])) {
        $tipo_operacao = $_GET['tipo_operacao'];
    }

    // Configurar período
    $start_date = "$year-$month-01 00:00:00";
    $end_date = date('Y-m-t 23:59:59', strtotime($start_date));
    
    // Dicionário para os nomes dos meses em português
    $meses_pt = [
        1 => 'Janeiro',
        2 => 'Fevereiro',
        3 => 'Março',
        4 => 'Abril',
        5 => 'Maio',
        6 => 'Junho',
        7 => 'Julho',
        8 => 'Agosto',
        9 => 'Setembro',
        10 => 'Outubro',
        11 => 'Novembro',
        12 => 'Dezembro'
    ];
    
    $current_month_name = $meses_pt[intval($month)] . " de " . $year;
    
    echo "<div class='ms-1 me-1'>";
    
    // Tabela de transações (versão compacta)
echo "<div class='card card-sm'>";
echo "<div class='card-header d-flex justify-content-between align-items-center pt-2 pb-2 ps-3 pe-3' style='background-color: #f8d775; color: #555;'>";
echo "<h5 class='m-0'><i class='fas fa-history'></i>&nbsp;" . __('Histórico - ', 'custosdeslocamento') . " $current_month_name</h5>";
echo "</div>";

// Buscar nome da entidade com ID 25 para mostrar nas entradas de fundos
$entityName = '';
$entityQuery = "SELECT name FROM glpi_entities WHERE id = 25";
$entityResult = $DB->query($entityQuery);
if ($entityResult && $DB->numrows($entityResult) > 0) {
    $entityData = $DB->fetchAssoc($entityResult);
    $entityName = $entityData['name'];
} else {
    $entityName = "Entidade ID 25";
}

// Definir o limite de itens por página
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 25; // Valor padrão: 25 itens
$start = isset($_GET['start']) ? intval($_GET['start']) : 0;

// Query with filter
$query = "SELECT c.*, 
         u.name as usuario_nome,
         v.entities_id as viagem_entidade,
         e.name as entidade_nome,
         v.tecnico_id as viagem_tecnico_id,
         tec.name as tecnico_nome,
         v.id as viagem_id
         FROM glpi_plugin_custosdeslocamento_caixa c
         LEFT JOIN glpi_users u ON c.usuario_id = u.id
         LEFT JOIN glpi_plugin_custosdeslocamento_viagens v ON c.viagem_id = v.id
         LEFT JOIN glpi_entities e ON v.entities_id = e.id
         LEFT JOIN glpi_users tec ON v.tecnico_id = tec.id
         WHERE c.data_hora >= '$start_date'
         AND c.data_hora <= '$end_date'";

// Adicionar filtro por tipo de operação se estiver definido
if (!empty($tipo_operacao)) {
    $query .= " AND c.tipo_operacao = '$tipo_operacao'";
}

// Salvar a consulta para contagem total (sem LIMIT)
$count_query = $query;

// Adicionar ordenação e limite à consulta principal
$query .= " ORDER BY c.data_hora DESC LIMIT $start, $limit";

// Executar a consulta
$result = $DB->query($query);
$count = $DB->numrows($result);

// Consulta para obter o total de registros (para paginação)
$result_total = $DB->query($count_query);
$total_registros = $DB->numrows($result_total);

// Início da tabela com design compacto
echo "<div class='table-responsive'>";
echo "<table class='table table-sm table-striped table-hover mb-0'>";

if ($count > 0) {
    echo "<thead style='background-color: #f5f5f5;'>";
    echo "<tr>";
    // Cabeçalhos da tabela
    echo "<th>" . __('Data/Hora', 'custosdeslocamento') . "</th>";
    echo "<th>" . __('Entidade', 'custosdeslocamento') . "</th>";
    echo "<th>" . __('Tipo', 'custosdeslocamento') . "</th>";
    echo "<th>" . __('Ticket', 'custosdeslocamento') . "</th>";
    echo "<th class='text-end'>" . __('Valor', 'custosdeslocamento') . "</th>";
    echo "<th class='text-end'>" . __('Saldo Ant.', 'custosdeslocamento') . "</th>";
    echo "<th class='text-end'>" . __('Saldo Post.', 'custosdeslocamento') . "</th>";
    echo "<th>" . __('Técnico', 'custosdeslocamento') . "</th>";
    echo "<th class='text-center'>" . __('Obs.', 'custosdeslocamento') . "</th>";
    echo "<th>" . __('Comprovantes', 'custosdeslocamento') . "</th>";
    echo "</tr>";
    echo "</thead>";
    
    echo "<tbody>";
    while ($data = $DB->fetchAssoc($result)) {
        echo "<tr>";
        
        // Data e Hora
        echo "<td>" . Html::convDateTime($data['data_hora']) . "</td>";
        
        // Entidade (Cliente) - Modificada para mostrar entidade ID 25 para entradas
        echo "<td>";
        if ($data['tipo_operacao'] == 'Entrada') {
            echo $entityName; // Mostra a entidade com ID 25 para entradas de fundos
        } else if (!empty($data['entidade_nome'])) {
            echo $data['entidade_nome']; // Mantém a exibição normal para outros casos
        } else {
            echo "-";
        }
        echo "</td>";
        
        // Tipo de Operação com badge colorido
        $badgeStyle = ($data['tipo_operacao'] == 'Entrada') ? 'background-color: #8ad3b0; color: #285e3b;' : 'background-color: #f3a4a4; color: #721c24;';
        $badgeIcon = ($data['tipo_operacao'] == 'Entrada') ? 'fas fa-arrow-up' : 'fas fa-arrow-down';
        echo "<td><span class='badge' style='$badgeStyle'><i class='$badgeIcon'></i>&nbsp;" . $data['tipo_operacao'] . "</span></td>";
        
        // Ticket - Modificado para mostrar "Adição de fundos" para entradas
        echo "<td>";
        if ($data['tipo_operacao'] == 'Entrada') {
            echo "Adição de fundos"; // Texto fixo para entradas de fundos
        } else if ($data['ticket_id'] > 0) {
            echo "<a href='" . $CFG_GLPI['root_doc'] . "/front/ticket.form.php?id=" . 
                  $data['ticket_id'] . "' target='_blank' class='btn btn-xs btn-outline-secondary'>";
            echo "<i class='fas fa-ticket-alt'></i>&nbsp;#" . $data['ticket_id'] . "</a>";
        } else {
            echo "-";
        }
        echo "</td>";
        
        // Valor
        $valor_style = ($data['valor'] < 0) ? 'color: #dc3545;' : 'color: #28a745;';
        echo "<td class='text-end' style='$valor_style'>";
        echo "R$ " . number_format(abs($data['valor']), 2, ',', '.') . "</td>";
        
        // Saldo Anterior
        echo "<td class='text-end'>R$ " . number_format($data['saldo_anterior'], 2, ',', '.') . "</td>";
        
        // Saldo Posterior
        echo "<td class='text-end'>R$ " . number_format($data['saldo_posterior'], 2, ',', '.') . "</td>";
        
        // Técnico Envolvido
        if (!empty($data['tecnico_nome']) && $data['tipo_operacao'] == 'Despesa' && $data['viagem_id'] > 0) {
            echo "<td>" . $data['tecnico_nome'] . "</td>";
        } else if ($data['tipo_operacao'] == 'Entrada') {
            // Para entradas, mostra o usuário que adicionou o fundo
            echo "<td>" . (!empty($data['usuario_nome']) ? $data['usuario_nome'] : getUserName($data['usuario_id'])) . "</td>";
        } else {
            // Fallback para outros casos
            echo "<td>" . (!empty($data['usuario_nome']) ? $data['usuario_nome'] : getUserName($data['usuario_id'])) . "</td>";
        }
        
        // Observação - Apenas ícone de olho
        $observacao = !empty($data['observacao']) ? htmlspecialchars($data['observacao']) : '-';
        echo "<td class='text-center'>";
        if (!empty($data['observacao']) && $data['observacao'] != '-') {
            echo "<a href='javascript:void(0)' onclick='mostrarComentario(\"" . addslashes($observacao) . "\")' title='" . __('Ver observação', 'custosdeslocamento') . "'>";
            echo "<i class='fas fa-eye' style='color: #6c757d;'></i>";
            echo "</a>";
        } else {
            echo "<i class='fas fa-minus' style='color: #d3d3d3;'></i>";
        }
        echo "</td>";
        
        // Comprovantes
        echo "<td>";
        
        // Se for uma despesa de viagem, buscar os comprovantes associados
        if ($data['tipo_operacao'] == 'Despesa' && $data['viagem_id'] > 0) {
            $documentQuery = "SELECT d.id, d.filename 
                             FROM glpi_documents d
                             INNER JOIN glpi_documents_items di ON d.id = di.documents_id
                             WHERE di.items_id = " . $data['viagem_id'] . "
                             AND di.itemtype = 'PluginCustosdeslocamentoViagem'";
            
            $documentResult = $DB->query($documentQuery);
            
            if ($documentResult && $DB->numrows($documentResult) > 0) {
                echo "<div class='dropdown'>";
                echo "<button class='btn btn-xs btn-info dropdown-toggle' type='button' data-bs-toggle='dropdown' style='background-color: #6cc3d5; border-color: #6cc3d5;'>";
                echo "<i class='fas fa-file'></i>&nbsp;" . $DB->numrows($documentResult);
                echo "</button>";
                echo "<ul class='dropdown-menu dropdown-menu-end'>";
                
                while ($doc = $DB->fetchAssoc($documentResult)) {
                    $docLink = $CFG_GLPI['root_doc'] . "/front/document.send.php?docid=" . $doc['id'];
                    echo "<li><a class='dropdown-item' href='$docLink' target='_blank'>";
                    echo "<i class='fas fa-download'></i>&nbsp;" . $doc['filename'] . "</a></li>";
                }
                
                echo "</ul>";
                echo "</div>";
                
            } else {
                // Verificar na tabela de relações específica do plugin
                $documentQuery2 = "SELECT d.id, d.filename 
                                  FROM glpi_documents d
                                  INNER JOIN glpi_plugin_custosdeslocamento_viagens_documents vd ON d.id = vd.documents_id
                                  WHERE vd.viagens_id = " . $data['viagem_id'];
                
                $documentResult2 = $DB->query($documentQuery2);
                
                if ($documentResult2 && $DB->numrows($documentResult2) > 0) {
                    echo "<div class='dropdown'>";
                    echo "<button class='btn btn-xs btn-info dropdown-toggle' type='button' data-bs-toggle='dropdown' style='background-color: #6cc3d5; border-color: #6cc3d5;'>";
                    echo "<i class='fas fa-file'></i>&nbsp;" . $DB->numrows($documentResult2);
                    echo "</button>";
                    echo "<ul class='dropdown-menu dropdown-menu-end'>";
                    
                    while ($doc = $DB->fetchAssoc($documentResult2)) {
                        $docLink = $CFG_GLPI['root_doc'] . "/front/document.send.php?docid=" . $doc['id'];
                        echo "<li><a class='dropdown-item' href='$docLink' target='_blank'>";
                        echo "<i class='fas fa-download'></i>&nbsp;" . $doc['filename'] . "</a></li>";
                    }
                    
                    echo "</ul>";
                    echo "</div>";
                    
                } else {
                    echo "<span class='badge bg-secondary' style='background-color: #e9ecef; color: #6c757d;'>0</span>";
                }
            }
        } else {
            echo "<span class='badge bg-secondary' style='background-color: #e9ecef; color: #6c757d;'>N/A</span>";
        }
        
        echo "</td>";
        echo "</tr>";
    }
    echo "</tbody>";

    // Adicionar informações de paginação
    if ($total_registros > $limit) {
        $total_paginas = ceil($total_registros / $limit);
        $pagina_atual = floor($start / $limit) + 1;
        
        echo "<tfoot>";
        echo "<tr>";
        echo "<td colspan='10' class='p-2'>";
        
        // Container para paginação
        echo "<div class='d-flex justify-content-between align-items-center'>";
        
        // Informações sobre registros
        echo "<div class='small text-muted'>";
        echo __('Exibindo', 'custosdeslocamento') . ' ' . ($start + 1) . ' ' . __('a', 'custosdeslocamento') . ' ' . 
            min($start + $limit, $total_registros) . ' ' . __('de', 'custosdeslocamento') . ' ' . 
            $total_registros . ' ' . __('registros', 'custosdeslocamento');
        echo "</div>";
        
        // Botões de navegação
        echo "<div class='btn-group'>";
        
        // Botão Anterior
        $prev_disabled = ($pagina_atual == 1) ? 'disabled' : '';
        $prev_start = max(0, ($pagina_atual - 2) * $limit);
        echo "<a href='?month=$month&year=$year&tipo_operacao=" . urlencode($tipo_operacao) . "&limit=$limit&start=$prev_start' class='btn btn-sm btn-outline-secondary $prev_disabled'>";
        echo "<i class='fas fa-chevron-left'></i>";
        echo "</a>";
        
        // Exibir páginas
        $window_size = 3; // Número de páginas visíveis ao redor da atual
        for ($i = max(1, $pagina_atual - $window_size); $i <= min($total_paginas, $pagina_atual + $window_size); $i++) {
            $active = ($i == $pagina_atual) ? 'active' : '';
            $page_start = ($i - 1) * $limit;
            echo "<a href='?month=$month&year=$year&tipo_operacao=" . urlencode($tipo_operacao) . "&limit=$limit&start=$page_start' class='btn btn-sm btn-outline-secondary $active'>$i</a>";
        }
        
        // Botão Próximo
        $next_disabled = ($pagina_atual >= $total_paginas) ? 'disabled' : '';
        $next_start = $pagina_atual * $limit;
        echo "<a href='?month=$month&year=$year&tipo_operacao=" . urlencode($tipo_operacao) . "&limit=$limit&start=$next_start' class='btn btn-sm btn-outline-secondary $next_disabled'>";
        echo "<i class='fas fa-chevron-right'></i>";
        echo "</a>";
        
        echo "</div>"; // Fecha btn-group
        echo "</div>"; // Fecha d-flex
        
        echo "</td>";
        echo "</tr>";
        echo "</tfoot>";
    }
        
        // Calcular totais do período com o mesmo filtro de tipo de operação
        $queryTotais = "SELECT 
                       SUM(CASE WHEN tipo_operacao = 'Entrada' THEN valor ELSE 0 END) as total_entradas,
                       SUM(CASE WHEN tipo_operacao = 'Despesa' THEN valor ELSE 0 END) as total_despesas
                       FROM glpi_plugin_custosdeslocamento_caixa
                       WHERE data_hora >= '$start_date'
                       AND data_hora <= '$end_date'";
                       
        // Adicionar o mesmo filtro de tipo para os totais, se especificado
        if (!empty($tipo_operacao)) {
            $queryTotais .= " AND tipo_operacao = '$tipo_operacao'";
        }
        
        $resultTotais = $DB->query($queryTotais);
        $dataTotais = $DB->fetchAssoc($resultTotais);
        
        $total_entradas = floatval($dataTotais['total_entradas']);
        $total_despesas = abs(floatval($dataTotais['total_despesas']));
        $saldo_periodo = $total_entradas - $total_despesas;
        
        // Rodapé com totais
        echo "<tfoot style='background-color: #f5f5f5;'>";
        echo "<tr>";
        echo "<td colspan='4' class='text-end'><b>" . __('Totais do Período:', 'custosdeslocamento') . "</b></td>";
        echo "<td class='text-end'>";
        echo "<span style='color: #28a745;'>+R$ " . number_format($total_entradas, 2, ',', '.') . "</span><br>";
        echo "<span style='color: #dc3545;'>-R$ " . number_format($total_despesas, 2, ',', '.') . "</span>";
        echo "</td>";
        echo "<td></td>";
        
        // Saldo do período com cor condicional
        $saldoStyle = $saldo_periodo >= 0 ? 'color: #28a745;' : 'color: #dc3545;';
        echo "<td class='text-end' style='$saldoStyle'>";
        echo "R$ " . number_format($saldo_periodo, 2, ',', '.') . "</td>";
        
        echo "<td colspan='3'></td>";
        echo "</tr>";
        echo "</tfoot>";
        
    } else {
        echo "<tr>";
        echo "<td class='text-center' colspan='10'>";
        echo __('Nenhuma transação encontrada para o período selecionado', 'custosdeslocamento');
        echo "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    echo "</div>"; // fecha table-responsive
    
    echo "</div>"; // fecha card
    echo "</div>"; // fecha container
}
}