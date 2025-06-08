<?php
// Inclusão de arquivos essenciais do GLPI
include('../../../inc/includes.php');

// Verificação de ativação do plugin
$plugin = new Plugin();
if (!$plugin->isActivated('custosdeslocamento')) {
   Html::displayNotFoundError();
}




// Configuração de log para depuração
$log_file = GLPI_LOG_DIR . '/relatorio_custosdeslocamento.log';
file_put_contents($log_file, date('Y-m-d H:i:s') . " - Início da execução\n");

/**
 * Função para registrar mensagens de depuração no arquivo de log
 * @param string $message A mensagem a ser registrada
 */
function log_debug($message) {
    global $log_file;
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND);
}

// Verificação de solicitação de exportação
if (isset($_GET['export']) && !empty($_GET['export'])) {
    $export_type = $_GET['export'];
    
    if ($export_type == 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=relatorio_viagens_' . date('Y-m-d') . '.csv');
        
        exportarRelatorioCSV();
        exit();
    } elseif ($export_type == 'pdf') {
        exportarRelatorioPDF();
        exit();
    }
}

// Inicialização do cabeçalho da página
Html::header(__('Relatórios de Deslocamento', 'custosdeslocamento'), $_SERVER['PHP_SELF'], 'custosdeslocamento', 'custosdeslocamento');

// Definição das variáveis globais
global $DB;

/**
 * BLOCO: Processamento de Parâmetros e Consultas ao Banco de Dados 
 * Obtém e processa os parâmetros de filtro com segurança
 */

// Obtenção e validação de parâmetros de filtro com tratamento adequado
$start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$id = isset($_GET['id']) && intval($_GET['id']) > 0 ? intval($_GET['id']) : 0;
$entities_id = isset($_GET['entities_id']) && intval($_GET['entities_id']) > 0 ? intval($_GET['entities_id']) : 0;
$tipo_ticket = isset($_GET['tipo_ticket']) && !empty($_GET['tipo_ticket']) ? Toolbox::cleanInputText($_GET['tipo_ticket']) : '';
$ticket_id = isset($_GET['ticket_id']) && intval($_GET['ticket_id']) > 0 ? intval($_GET['ticket_id']) : 0;
$tecnico_id = isset($_GET['tecnico_id']) && intval($_GET['tecnico_id']) > 0 ? intval($_GET['tecnico_id']) : 0;
$origem_id = isset($_GET['origem_id']) && intval($_GET['origem_id']) > 0 ? intval($_GET['origem_id']) : 0;
$destino_id = isset($_GET['destino_id']) && intval($_GET['destino_id']) > 0 ? intval($_GET['destino_id']) : 0;
$status = isset($_GET['status']) && !empty($_GET['status']) ? Toolbox::cleanInputText($_GET['status']) : '';
$tipo_operacao = isset($_GET['tipo_operacao']) && !empty($_GET['tipo_operacao']) ? Toolbox::cleanInputText($_GET['tipo_operacao']) : '';

// Configuração de paginação com valores padrão seguros
$limit = isset($_GET['limit']) && intval($_GET['limit']) > 0 ? intval($_GET['limit']) : 50;
$start = isset($_GET['start']) && intval($_GET['start']) >= 0 ? intval($_GET['start']) : 0;

log_debug("Filtros: start_date=$start_date, end_date=$end_date, id=$id, entities_id=$entities_id, tipo_ticket=$tipo_ticket, ticket_id=$ticket_id, tecnico_id=$tecnico_id, origem_id=$origem_id, destino_id=$destino_id, status=$status, tipo_operacao=$tipo_operacao");

/**
 * BLOCO: Consultas para Estatísticas e Dados de Dashboard
 * Obtém informações gerais para exibição no dashboard
 */
 
// Consulta para obter estatísticas gerais
$query_estatisticas = "SELECT 
    COUNT(DISTINCT v.id) as total_viagens,
    SUM(v.custo) as total_custo,
    COUNT(DISTINCT v.tecnico_id) as total_tecnicos,
    COUNT(DISTINCT v.entities_id) as total_Clientes
FROM glpi_plugin_custosdeslocamento_viagens v";

log_debug("Executando consulta de estatísticas: $query_estatisticas");
$result_stats = $DB->query($query_estatisticas);
$estatisticas = $DB->fetchAssoc($result_stats);

// Obter o saldo atual para exibição
$query_saldo = "SELECT saldo_posterior as saldo_atual 
                FROM glpi_plugin_custosdeslocamento_caixa 
                ORDER BY id DESC LIMIT 1";
                
log_debug("Executando consulta de saldo: $query_saldo");
$result_saldo = $DB->query($query_saldo);
$saldo = $DB->fetchAssoc($result_saldo);

/**
 * BLOCO: Consultas para Opções de Filtro
 * Obtém dados diretamente do banco para preencher os dropdowns de filtro
 */
 
// Consulta para obter todos os tipos de ticket disponíveis
$query_tipos_ticket = "SELECT DISTINCT tipo_ticket 
                      FROM glpi_plugin_custosdeslocamento_viagens 
                      WHERE tipo_ticket IS NOT NULL AND tipo_ticket != ''
                      ORDER BY tipo_ticket";
$result_tipos_ticket = $DB->query($query_tipos_ticket);
$tipos_ticket = [];
while ($row = $DB->fetchAssoc($result_tipos_ticket)) {
    $tipos_ticket[$row['tipo_ticket']] = $row['tipo_ticket'];
}

// Consulta para obter todos os status disponíveis
$query_status = "SELECT DISTINCT status 
                FROM glpi_plugin_custosdeslocamento_viagens 
                WHERE status IS NOT NULL AND status != ''
                ORDER BY status";
$result_status = $DB->query($query_status);
$status_options = [''=>__('Todos')];
while ($row = $DB->fetchAssoc($result_status)) {
    $status_options[$row['status']] = __($row['status'], 'custosdeslocamento');
}

// Consulta para obter todos os tipos de operação disponíveis
$query_tipos_operacao = "SELECT DISTINCT tipo_operacao 
                        FROM glpi_plugin_custosdeslocamento_caixa 
                        WHERE tipo_operacao IS NOT NULL AND tipo_operacao != ''
                        ORDER BY tipo_operacao";
$result_tipos_operacao = $DB->query($query_tipos_operacao);
$tipos_operacao = [''=>__('Todos')];
while ($row = $DB->fetchAssoc($result_tipos_operacao)) {
    $tipos_operacao[$row['tipo_operacao']] = $row['tipo_operacao'];
}

/**
 * BLOCO: Estilos CSS para a interface do relatório
 * Define toda a estilização necessária para os componentes visuais
 */
echo '<style>
    /* Estilos para o filtro personalizado */
    .custom-filter {
        margin-bottom: 15px;
        background-color: #f5f5f5;
        padding: 15px;
        border-radius: 5px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    /* Estrutura de linhas para organização dos filtros */
    .filter-row {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 10px;
    }
    
    /* Itens individuais do filtro */
    .filter-item {
        flex: 1;
        min-width: 200px;
    }
    
    .filter-item label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
    }
    
    /* Layout para ações do filtro */
    .filter-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px solid #ddd;
    }
    
    /* Botões de exportação */
    .export-buttons {
        display: flex;
        gap: 10px;
    }
    
    .export-btn {
        text-decoration: none;
        padding: 6px 12px;
        border-radius: 3px;
        font-weight: bold;
    }
    
    .export-csv {
        background-color: #28a745;
        color: white;
    }
    
    .export-pdf {
        background-color: #dc3545;
        color: white;
    }
    
    /* Estilos para os cards de dashboard */
    .dash-card {
        padding: 10px;
        margin-bottom: 10px;
        border-radius: 5px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        text-align: center;
        min-height: 50px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    
    /* Variações de cores para os cards */
    .dash-card-purple {
        background-color: #e6e6fa;
        color: #4a4a6a;
    }
    
    .dash-card-green {
        background-color: #d4edda;
        color: #155724;
    }
    
    .dash-card-blue {
        background-color: #d1ecf1;
        color: #0c5460;
    }
    
    .dash-card-red {
        background-color: #f8d7da;
        color: #721c24;
    }
    
    /* Formatação dos textos nos cards */
    .dash-card h5 {
        margin-top: 0;
        font-size: 12px;
        text-transform: uppercase;
    }
    
    .dash-card .value {
        font-size: 18px;
        font-weight: bold;
        margin: 5px 0;
    }
    
    /* Layout para o resumo do dashboard */
    .dash-summary {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .dash-summary-item {
        flex: 1;
        min-width: 200px;
    }
    
    /* Melhorias nas tabelas */
    .tab_cadre_fixehov th {
        background-color: #e0e0e0;
        padding: 8px;
    }
    
    .tab_cadre_fixehov td {
        padding: 8px;
    }
    
    /* Estilização da paginação */
    .pagination {
        display: flex;
        justify-content: center;
        margin: 20px 0;
    }
    
    .pagination a, .pagination span {
        padding: 5px 10px;
        margin: 0 5px;
        border: 1px solid #ddd;
        border-radius: 3px;
        text-decoration: none;
    }
    
    .pagination .current {
        background-color: #0275d8;
        color: white;
    }
    
    /* Estilos para o acordeão de filtros */
    .accordion {
        margin-bottom: 20px;
    }
    
    .accordion-button:not(.collapsed) {
        background-color: #f8f9fa;
        color: #0d6efd;
    }
    
    .accordion-button:focus {
        box-shadow: none;
        border-color: rgba(0,0,0,.125);
    }
    
    /* Espaçamentos adicionais */
    .me-2 {
        margin-right: 10px;
    }
    
    .mt-3 {
        margin-top: 15px;
    }
</style>';

/**
 * BLOCO: Exibição do Dashboard com Estatísticas
 * Mostra cards com indicadores de desempenho e resumo de dados
 */

// Mostrar estatísticas em cards
echo '<div class="dash-summary">';

// Card - Total de Viagens
echo '<div class="dash-summary-item">';
echo '<div class="dash-card dash-card-blue">';
echo '<h5>' . __('Total de Viagens', 'custosdeslocamento') . '</h5>';
echo '<div class="value">' . number_format($estatisticas['total_viagens'] ?? 0, 0, ',', '.') . '</div>';
echo '</div>';
echo '</div>';

// Card - Total de Custo
echo '<div class="dash-summary-item">';
echo '<div class="dash-card dash-card-red">';
echo '<h5>' . __('Total de Custos', 'custosdeslocamento') . '</h5>';
echo '<div class="value">R$ ' . number_format($estatisticas['total_custo'] ?? 0, 2, ',', '.') . '</div>';
echo '</div>';
echo '</div>';


// Card - Saldo Atual
echo '<div class="dash-summary-item">';
echo '<div class="dash-card dash-card-green">';
echo '<h5>' . __('Saldo Atual', 'custosdeslocamento') . '</h5>';
echo '<div class="value">R$ ' . number_format($saldo['saldo_atual'] ?? 0, 2, ',', '.') . '</div>';
echo '</div>';
echo '</div>';

echo '</div>'; // Fim do dashboard

/**
 * BLOCO: Formulário de Filtros
 * Implementa um acordeão com todos os filtros disponíveis
 */

// Criar o acordeão para os filtros
echo '<div class="accordion" id="filterAccordion">';
echo '<div class="accordion-item">';

// Cabeçalho do accordion (sempre visível)
echo '<h2 class="accordion-header" id="headingFilters">';
echo '<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFilters" aria-expanded="false" aria-controls="collapseFilters">';
echo '<i class="ti ti-filter me-2"></i>' . __('Filtros de Pesquisa', 'custosdeslocamento');
echo '</button>';
echo '</h2>';

// Conteúdo do accordion (inicialmente fechado)
echo '<div id="collapseFilters" class="accordion-collapse collapse" aria-labelledby="headingFilters" data-bs-parent="#filterAccordion">';
echo '<div class="accordion-body">';

// Início do formulário
echo '<form method="get" action="' . $_SERVER['PHP_SELF'] . '">';

// Primeira linha de filtros
echo '<div class="filter-row">';

// Cliente (Entidade)
echo '<div class="filter-item">';
echo '<label for="entities_id">' . __('Cliente', 'custosdeslocamento') . '</label>';
Entity::dropdown([
    'name' => 'entities_id',
    'value' => $entities_id,
    'entity' => $_SESSION['glpiactiveentities'],
    'display_emptychoice' => true,
    'condition' => [
        'id' => ['!=', 0],  // Exclui a entidade 0
        'entities_id' => ['=', 0]  // Apenas entidades pai
    ]
]);
echo '</div>';

// ID do Ticket
echo '<div class="filter-item">';
echo '<label for="ticket_id">' . __('ID do Ticket', 'custosdeslocamento') . '</label>';
echo '<input type="text" class="form-control" id="ticket_id" name="ticket_id" value="' . ($ticket_id > 0 ? $ticket_id : '') . '" placeholder="10 dígitos" maxlength="10">';
echo '</div>';

// Técnico
echo '<div class="filter-item">';
echo '<label for="tecnico_id">' . __('Técnico Envolvido', 'custosdeslocamento') . '</label>';
User::dropdown([
    'name' => 'tecnico_id',
    'value' => $tecnico_id,
    'right' => 'all',
    'entity' => $_SESSION['glpiactiveentities'],
    'display_emptychoice' => true
]);
echo '</div>';

// Itens por página
echo '<div class="filter-item">';
echo '<label for="limit">' . __('Itens por página', 'custosdeslocamento') . '</label>';
$limit_options = [
    5 => '5',
    10 => '10',
    25 => '25',
    50 => '50',
    100 => '100',
    1000 => '1000'
];
Dropdown::showFromArray('limit', $limit_options, [
    'value' => $limit,
    'display_emptychoice' => false
]);
echo '</div>';

echo '</div>'; // Fim da primeira linha de filtros

// Segunda linha de filtros
echo '<div class="filter-row">';

// Período - Data inicial
echo '<div class="filter-item">';
echo '<label for="start_date">' . __('Data Inicial', 'custosdeslocamento') . '</label>';
Html::showDateField('start_date', ['value' => $start_date, 'display' => true]);
echo '</div>';

// Período - Data final
echo '<div class="filter-item">';
echo '<label for="end_date">' . __('Data Final', 'custosdeslocamento') . '</label>';
Html::showDateField('end_date', ['value' => $end_date, 'display' => true]);
echo '</div>';


// Tipo de Ticket (filtro frontend)
echo '<div class="filter-item">';
echo '<label for="tipo_ticket_filter">' . __('Tipo de Ticket', 'custosdeslocamento') . '</label>';
echo '<select id="tipo_ticket_filter" class="form-control" onchange="filtrarTipoTicketFrontend()">';
echo '<option value="Todos">Todos</option>';
echo '<option value="Ticket">Ticket</option>';
echo '<option value="Mudança">Mudança</option>';
echo '<option value="Problema">Problema</option>';
echo '<option value="Projeto">Projeto</option>';
echo '</select>';
echo '</div>';

// Status (filtro frontend)
echo '<div class="filter-item">';
echo '<label for="status_filter">' . __('Status', 'custosdeslocamento') . '</label>';
echo '<select id="status_filter" class="form-control" onchange="filtrarStatusFrontend()">';
echo '<option value="Todos">Todos</option>';
echo '<option value="Efetuada">Efetuada</option>';
echo '<option value="Cancelada">Cancelada</option>';
echo '</select>';
echo '</div>';

echo '</div>'; // Fim da segunda linha de filtros

// Adicionar campos ocultos para manter parâmetros importantes
echo '<input type="hidden" name="itemtype" value="PluginCustosdeslocamentoDeslocamento">';
if (isset($_GET['start'])) {
    echo '<input type="hidden" name="start" value="' . $_GET['start'] . '">';
}

// Preparar URL para exportação
$current_url = $_SERVER['REQUEST_URI'];
$url_components = parse_url($current_url);
$query_params = [];
if (isset($url_components['query'])) {
    parse_str($url_components['query'], $query_params);
}

// Remover parâmetros de paginação para exportação
unset($query_params['start']);
unset($query_params['limit']);

// Adicionar parâmetro de exportação
$query_params['export'] = 'csv';
$export_csv_url = $_SERVER['PHP_SELF'] . '?' . http_build_query($query_params);

$query_params['export'] = 'pdf';
$export_pdf_url = $_SERVER['PHP_SELF'] . '?' . http_build_query($query_params);

// Barra de botões única
echo '<div class="filter-actions mt-3">';
// Botão de filtrar - Azul claro
echo '<button type="submit" class="btn me-2" name="search" value="1" style="background-color: #d4edf7; border-color: #bcdce8; color: #004466;">' . __('Filtrar', 'custosdeslocamento') . '</button>';

// Botão de limpar filtros - Rosa claro
echo '<button type="button" class="btn me-2" id="btn-limpar-filtros" style="background-color: #f8d7da; border-color: #f1b0b7; color: #721c24;">' . __('Limpar Filtros', 'custosdeslocamento') . '</button>';

// Botões de exportação
echo '<a href="' . $export_csv_url . '" class="btn me-2 export-btn" style="background-color: #d4edda; border-color: #c3e6cb; color: #155724;">' . __('Exportar CSV', 'custosdeslocamento') . '</a>';
echo '<a href="' . $export_pdf_url . '" class="btn export-btn" style="background-color: #e2e3f3; border-color: #c8cbe5; color: #383d73;">' . __('Exportar PDF', 'custosdeslocamento') . '</a>';

echo '</div>'; // Fim dos botões de ação

echo '</form>';
echo '</div>'; // Fim do accordion-body
echo '</div>'; // Fim do accordion-collapse
echo '</div>'; // Fim do accordion-item
echo '</div>'; // Fim do accordion

/**
 * BLOCO: Script para Limpeza de Filtros e Comportamento do Acordeão
 * Implementa interatividade na interface de usuário
 */
echo '<script type="text/javascript">



document.addEventListener("DOMContentLoaded", function() {
    // Função para limpar filtros
    document.getElementById("btn-limpar-filtros").addEventListener("click", function() {
        // Redireciona mantendo apenas os parâmetros essenciais do sistema
        let baseUrl = "' . $_SERVER['PHP_SELF'] . '";
        let params = new URLSearchParams();
        
        // Manter apenas parâmetros de sistema necessários
        if (new URLSearchParams(window.location.search).has("itemtype")) {
            params.append("itemtype", "PluginCustosdeslocamentoDeslocamento");
        }
        
        window.location.href = baseUrl + (params.toString() ? "?" + params.toString() : "");
    });

    // Verificar se há algum filtro aplicado para abrir automaticamente o acordeão
    let urlParams = new URLSearchParams(window.location.search);
    let hasFilters = false;
    
    // Verifique se algum dos filtros foi aplicado
    ["entities_id", "ticket_id", "id", "tecnico_id", "origem_id", "destino_id", "status", "tipo_ticket", "tipo_operacao", "start_date", "end_date"].forEach(function(param) {
        if (urlParams.has(param) && urlParams.get(param) !== "") {
            hasFilters = true;
        }
    });

    // Se algum filtro foi aplicado, abra o acordeão automaticamente
    if (hasFilters) {
        var myCollapsible = document.getElementById("collapseFilters");
        if (typeof bootstrap !== "undefined" && bootstrap.Collapse) {
            var bsCollapse = new bootstrap.Collapse(myCollapsible, {
                toggle: true
            });
        } else {
            // Fallback para caso bootstrap não esteja disponível
            myCollapsible.classList.add("show");
        }
    }
    
    // Atualizar automaticamente ao selecionar datas
    document.querySelectorAll("#start_date, #end_date").forEach(function(element) {
        element.addEventListener("change", function() {
            var form = this.closest("form");
            var hiddenInput = document.createElement("input");
            hiddenInput.type = "hidden";
            hiddenInput.name = "start";
            hiddenInput.value = "0";
            form.appendChild(hiddenInput);
            form.submit();
        });
    });
    
    // Atualizar a página quando o limite for alterado
    document.querySelector("#limit").addEventListener("change", function() {
        var form = this.closest("form");
        var hiddenInput = document.createElement("input");
        hiddenInput.type = "hidden";
        hiddenInput.name = "start";
        hiddenInput.value = "0";
        form.appendChild(hiddenInput);
        form.submit();
    });
});
</script>';

/**
 * BLOCO: Consulta Principal e Exibição dos Resultados
 * Obtém e exibe os dados do relatório com base nos filtros aplicados
 */

try {
    // Construir consulta base SQL para relatório
    $base_sql = "SELECT 
        v.id, 
        v.entities_id, 
        v.tipo_ticket, 
        v.ticket_id, 
        v.ticket_titulo, 
        v.data_hora, 
        v.tecnico_id, 
        v.origem_id, 
        v.destino_id, 
        v.custo, 
        v.status, 
        v.comentarios,
        v.date_creation,
        v.date_mod,
        c.tipo_operacao,
        c.saldo_anterior,
        c.saldo_posterior,
        c.observacao
    FROM glpi_plugin_custosdeslocamento_viagens v
    LEFT JOIN glpi_plugin_custosdeslocamento_caixa c ON c.viagem_id = v.id";

    // Array de condições SQL com tratamento de escape
    $conditions = [];
    
    // Aplicar filtros com tratamento adequado para evitar SQL injection
    if ($id > 0) {
        $conditions[] = "v.id = " . $DB->escape($id);
    }
    
    if ($entities_id > 0) {
        $conditions[] = "v.entities_id = " . $DB->escape($entities_id);
    }
    
    if (!empty($tipo_ticket)) {
        $conditions[] = "v.tipo_ticket = '" . $DB->escape($tipo_ticket) . "'";
    }
    
    if ($ticket_id > 0) {
        $conditions[] = "v.ticket_id = " . $DB->escape($ticket_id);
    }
    
    if (!empty($start_date)) {
        $conditions[] = "DATE(v.data_hora) >= '" . $DB->escape($start_date) . "'";
    }
    
    if (!empty($end_date)) {
        $conditions[] = "DATE(v.data_hora) <= '" . $DB->escape($end_date) . "'";
    }
    
    if ($tecnico_id > 0) {
        $conditions[] = "v.tecnico_id = " . $DB->escape($tecnico_id);
    }
    
    if ($origem_id > 0) {
        $conditions[] = "v.origem_id = " . $DB->escape($origem_id);
    }
    
    if ($destino_id > 0) {
        $conditions[] = "v.destino_id = " . $DB->escape($destino_id);
    }
    
    /*
    if (!empty($status)) {
        $conditions[] = "v.status = '" . $DB->escape($status) . "'";
    }*/
    
    if (!empty($tipo_operacao)) {
        $conditions[] = "c.tipo_operacao = '" . $DB->escape($tipo_operacao) . "'";
    }
    
    // Adicionar cláusula WHERE se houver condições
    if (!empty($conditions)) {
        $base_sql .= " WHERE " . implode(" AND ", $conditions);
    }
    
    // Consulta para contagem total de registros
    $count_sql = str_replace("SELECT 
        v.id, 
        v.entities_id, 
        v.tipo_ticket, 
        v.ticket_id, 
        v.ticket_titulo, 
        v.data_hora, 
        v.tecnico_id, 
        v.origem_id, 
        v.destino_id, 
        v.custo, 
        v.status, 
        v.comentarios,
        v.date_creation,
        v.date_mod,
        c.tipo_operacao,
        c.saldo_anterior,
        c.saldo_posterior,
        c.observacao", "SELECT COUNT(*) as total", $base_sql);
    
    log_debug("Executando contagem: $count_sql");
    
    $result_count = $DB->query($count_sql);
    $total_registros = $DB->result($result_count, 0, 'total');
    
    // Total de páginas para paginação
    $total_pages = ceil($total_registros / $limit);
    
    // Consulta final com ordenação e limites para paginação
    $final_sql = $base_sql . " ORDER BY v.data_hora DESC";
    
    // Adicionar limites apenas se não for para exportação
    if (!isset($_GET['export'])) {
        $final_sql .= " LIMIT " . intval($start) . ", " . intval($limit);
    }
    
    log_debug("Executando consulta principal: $final_sql");
    
    $result = $DB->query($final_sql);
    
    if ($result === false) {
        log_debug("Erro na consulta: " . $DB->error());
        throw new Exception("Erro na execução da consulta: " . $DB->error());
    }
    
    $num_rows = $DB->numrows($result);
    log_debug("Consulta retornou $num_rows linhas");
    
    // Verificar se há resultados
    if ($num_rows > 0) {
        // Exibir informações de paginação
        echo '<div class="pager_infos">';
        echo __('Exibindo') . ' ' . ($start + 1) . ' ' . __('a') . ' ' . min($start + $limit, $total_registros) . ' ' . __('de') . ' ' . $total_registros . ' ' . __('registros');
        echo '</div>';
        
        // Iniciar tabela com os resultados
        echo '<table class="tab_cadre_fixehov" style="width: 100%;">';
        echo '<thead>';
        echo '<th>' . __('Cliente', 'custosdeslocamento') . '</th>';
        echo '<th>' . __('Tipo de Ticket', 'custosdeslocamento') . '</th>';
        echo '<th>' . __('Ticket', 'custosdeslocamento') . '</th>';
        echo '<th>' . __('Data/Hora', 'custosdeslocamento') . '</th>';
        echo '<th>' . __('Técnico', 'custosdeslocamento') . '</th>';
        echo '<th>' . __('Origem', 'custosdeslocamento') . '</th>';
        echo '<th>' . __('Destino', 'custosdeslocamento') . '</th>';
        echo '<th>' . __('Custo', 'custosdeslocamento') . '</th>';
        echo '<th>' . __('Status', 'custosdeslocamento') . '</th>';
        echo '<th>' . __('Saldo Anterior', 'custosdeslocamento') . '</th>';
        echo '<th>' . __('Saldo Posterior', 'custosdeslocamento') . '</th>';
        echo '<th>' . __('Comentários', 'custosdeslocamento') . '</th>';
        echo '<th>' . __('Comprovantes', 'custosdeslocamento') . '</th>';
        echo '</tr>';
        echo '</thead>';
        
        echo '<tbody>';
        $i = 0;
        $total_custo = 0;
        
        while ($row = $DB->fetchAssoc($result)) {
            $rowClass = ($i++ % 2) ? 'tab_bg_1' : 'tab_bg_2';
            echo '<tr class="' . $rowClass . '">';
            
            
            // Cliente
            echo '<td>' . Dropdown::getDropdownName('glpi_entities', $row['entities_id']) . '</td>';
            
            // Tipo de Ticket
            echo '<td>' . $row['tipo_ticket'] . '</td>';
            
            // Ticket (com link)
            echo '<td>';
            if ($row['ticket_id'] > 0) {
                $ticketTypeURL = 'ticket';
                if ($row['tipo_ticket'] == 'Mudança') {
                    $ticketTypeURL = 'change';
                } elseif ($row['tipo_ticket'] == 'Problema') {
                    $ticketTypeURL = 'problem';
                } elseif ($row['tipo_ticket'] == 'Projeto') {
                    $ticketTypeURL = 'project';
                }
                
                echo '<a href="' . $CFG_GLPI['root_doc'] . '/front/' . $ticketTypeURL . '.form.php?id=' . $row['ticket_id'] . '" target="_blank">';
                echo '#' . $row['ticket_id'] . ' - ' . substr($row['ticket_titulo'], 0, 30) . (strlen($row['ticket_titulo']) > 30 ? '...' : '');
                echo '</a>';
            } else {
                echo '-';
            }
            echo '</td>';
            
            // Data/Hora
            echo '<td>' . Html::convDateTime($row['data_hora']) . '</td>';
            
            // Técnico
            echo '<td>' . getUserName($row['tecnico_id']) . '</td>';
            
            // Origem
            echo '<td>' . Dropdown::getDropdownName('glpi_entities', $row['origem_id']) . '</td>';
            
            // Destino
            echo '<td>' . Dropdown::getDropdownName('glpi_entities', $row['destino_id']) . '</td>';
            
            // Custo
            echo '<td style="text-align: right;">R$ ' . number_format($row['custo'], 2, ',', '.') . '</td>';
            $total_custo += $row['custo'];
            
            // Status com código de cores
            $statusClass = '';
            if ($row['status'] == 'Efetuada') {
                $statusClass = 'background-color: #d4edda; color: #155724;';
            } elseif ($row['status'] == 'Cancelada') {
                $statusClass = 'background-color: #f8d7da; color: #721c24;';
            } elseif ($row['status'] == 'Pendente') {
                $statusClass = 'background-color: #fff3cd; color: #856404;';
            }
            
            echo '<td><span style="padding: 2px 5px; border-radius: 3px; ' . $statusClass . '">' . $row['status'] . '</span></td>';
            
            // Saldo Anterior
            echo '<td style="text-align: right;">' . (!empty($row['saldo_anterior']) ? 'R$ ' . number_format($row['saldo_anterior'], 2, ',', '.') : '-') . '</td>';
            
            // Saldo Posterior
            echo '<td style="text-align: right;">' . (!empty($row['saldo_posterior']) ? 'R$ ' . number_format($row['saldo_posterior'], 2, ',', '.') : '-') . '</td>';
            
            // Comentários (ícone de olho)
echo '<td style="text-align: center;">';
if (!empty($row['comentarios'])) {
    echo '<a href="javascript:void(0);" class="view-comments-btn" data-comments="' . htmlspecialchars($row['comentarios']) . '"><i class="ti ti-eye" style="font-size: 16px; color: #0275d8;"></i></a>';
} else {
    echo '<i class="ti ti-eye-off" style="font-size: 16px; color: #ccc;"></i>';
}
echo '</td>';

        // Comprovantes
echo '<td style="text-align: center;">';

// Consultar documentos associados à viagem
$documentQuery = "SELECT d.id, d.filename 
                 FROM glpi_documents d
                 INNER JOIN glpi_documents_items di ON d.id = di.documents_id
                 WHERE di.items_id = " . $row['id'] . "
                 AND di.itemtype = 'PluginCustosdeslocamentoViagem'";

$documentResult = $DB->query($documentQuery);
$countDocs = ($documentResult) ? $DB->numrows($documentResult) : 0;

// Se não encontrar, tenta na tabela específica do plugin
if ($countDocs == 0) {
    $documentQuery2 = "SELECT d.id, d.filename 
                     FROM glpi_documents d
                     INNER JOIN glpi_plugin_custosdeslocamento_viagens_documents vd ON d.id = vd.documents_id
                     WHERE vd.viagens_id = " . $row['id'];
    
    $documentResult2 = $DB->query($documentQuery2);
    $countDocs2 = ($documentResult2) ? $DB->numrows($documentResult2) : 0;
    
    if ($countDocs2 > 0) {
        echo "<div class='dropdown'>";
        echo "<button class='btn btn-xs btn-info dropdown-toggle' type='button' data-bs-toggle='dropdown' style='background-color: #6cc3d5; border-color: #6cc3d5;'>";
        echo "<i class='fas fa-file'></i>&nbsp;" . $countDocs2;
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
} else {
    echo "<div class='dropdown'>";
    echo "<button class='btn btn-xs btn-info dropdown-toggle' type='button' data-bs-toggle='dropdown' style='background-color: #6cc3d5; border-color: #6cc3d5;'>";
    echo "<i class='fas fa-file'></i>&nbsp;" . $countDocs;
    echo "</button>";
    echo "<ul class='dropdown-menu dropdown-menu-end'>";
    
    while ($doc = $DB->fetchAssoc($documentResult)) {
        $docLink = $CFG_GLPI['root_doc'] . "/front/document.send.php?docid=" . $doc['id'];
        echo "<li><a class='dropdown-item' href='$docLink' target='_blank'>";
        echo "<i class='fas fa-download'></i>&nbsp;" . $doc['filename'] . "</a></li>";
    }
    
    echo "</ul>";
    echo "</div>";
    
    // Adicionar botão para visualizar no modal
    echo "&nbsp;<button class='btn btn-xs btn-outline-primary' onclick='mostrarComprovante(" . $row['id'] . ")'>";
    echo "<i class='fas fa-eye'></i>";
    echo "</button>";
}

echo '</td>';
            
            echo '</tr>';
        }
        
        // Linha com o total
echo '<tr class="tab_bg_1" style="font-weight: bold;">';
echo '<td colspan="8" style="text-align: right;">' . __('Total', 'custosdeslocamento') . ':</td>';
echo '<td style="text-align: right;">R$ ' . number_format($total_custo, 2, ',', '.') . '</td>';
echo '<td colspan="5"></td>'; // Aumentado para 5 para incluir a coluna de comprovantes
echo '</tr>';
        
        echo '</tbody>';
        echo '</table>';
        
        // Barra de paginação
        if ($total_pages > 1) {
            echo '<div class="pagination">';
            
            // Link para a primeira página
            echo '<a href="' . $_SERVER['PHP_SELF'] . '?' . http_build_query(array_merge($_GET, ['start' => 0])) . '">&laquo;</a>';
            
            // Mostrar algumas páginas antes e depois da atual
            $current_page = floor($start / $limit) + 1;
            $window_size = 5; // Número de páginas visíveis ao redor da página atual
            
            for ($page = max(1, $current_page - $window_size); $page <= min($total_pages, $current_page + $window_size); $page++) {
                $page_start = ($page - 1) * $limit;
                
                if ($page == $current_page) {
                    echo '<span class="current">' . $page . '</span>';
                } else {
                    echo '<a href="' . $_SERVER['PHP_SELF'] . '?' . http_build_query(array_merge($_GET, ['start' => $page_start])) . '">' . $page . '</a>';
                }
            }
            
            // Link para a última página
            $last_page_start = ($total_pages - 1) * $limit;
            echo '<a href="' . $_SERVER['PHP_SELF'] . '?' . http_build_query(array_merge($_GET, ['start' => $last_page_start])) . '">&raquo;</a>';
            
            echo '</div>'; // Fim da paginação
        }
    } else {
        // Nenhum resultado encontrado
        echo '<div class="alert alert-info" style="padding: 20px; margin: 20px 0; text-align: center; background-color: #d1ecf1; border: 1px solid #bee5eb; border-radius: 5px; color: #0c5460;">';
        echo '<i class="fa fa-info-circle" style="margin-right: 10px;"></i>' . __('Nenhum registro encontrado com os critérios selecionados', 'custosdeslocamento');
        echo '</div>';
    }
} catch (Exception $e) {
    // Tratamento de erro para evitar página em branco
    log_debug("Erro ao processar a consulta: " . $e->getMessage());
    
    echo '<div class="alert alert-danger" style="padding: 20px; margin: 20px 0; text-align: center; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; color: #721c24;">';
    echo '<i class="fa fa-exclamation-triangle" style="margin-right: 10px;"></i>' . __('Erro ao processar a consulta. Por favor, tente novamente ou contate o administrador.', 'custosdeslocamento');
    echo '</div>';
    
    // Detalhes de erro apenas visíveis para administradores
    if (Session::haveRight('config', UPDATE)) {
        echo '<div class="debug-info" style="margin-top: 20px; padding: 15px; background-color: #f8f9fa; border: 1px solid #ddd; border-radius: 5px;">';
        echo '<h3>Informações de Depuração (visível apenas para administradores)</h3>';
        echo '<p><strong>Erro:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p><strong>Arquivo:</strong> ' . htmlspecialchars($e->getFile()) . ' (linha ' . $e->getLine() . ')</p>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
        echo '</div>';
    }
}

// ADICIONE O CÓDIGO DO MODAL E JAVASCRIPT AQUI, ANTES DO Html::footer()
// Modal para visualização de comentários
echo '<div id="commentsModal" style="display: none; position: fixed; z-index: 1000; background-color: white; border-radius: 5px; width: 300px; max-height: 200px; box-shadow: 0 2px 10px rgba(0,0,0,0.2); overflow: auto;">
    <div style="padding: 10px; position: relative;">
        <span id="closeCommentModal" style="position: absolute; top: 5px; right: 10px; font-size: 16px; font-weight: bold; cursor: pointer;">&times;</span>
        <h4 style="margin-top: 0; margin-bottom: 10px; padding-bottom: 5px; border-bottom: 1px solid #eee; padding-right: 20px;">' . __('Comentários da Viagem', 'custosdeslocamento') . '</h4>
        <div id="commentContent" style="margin: 5px 0; white-space: pre-wrap;"></div>
    </div>
</div>';

// Modal para exibir comprovantes
echo '<div class="modal fade" id="comprovanteModal" tabindex="-1" aria-labelledby="comprovanteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="comprovanteModalLabel">' . __('Comprovantes', 'custosdeslocamento') . '</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body" id="comprovanteConteudo" style="text-align: center;"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . __('Fechar', 'custosdeslocamento') . '</button>
            </div>
        </div>
    </div>
</div>';

// Função para visualizar comprovantes no modal


echo '<script type="text/javascript">
function mostrarComprovante(viagemId) {
    var xhr = new XMLHttpRequest();
    xhr.open("GET", "' . $CFG_GLPI['root_doc'] . '/plugins/custosdeslocamento/ajax/getComprovantes.php?viagem_id=" + viagemId, true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            document.getElementById("comprovanteConteudo").innerHTML = xhr.responseText;
            var modal = new bootstrap.Modal(document.getElementById("comprovanteModal"));
            modal.show();
        }
    };
    xhr.send();
}

// Definir a função globalmente
window.mostrarComprovante = mostrarComprovante;

document.addEventListener("DOMContentLoaded", function() {
    // ===== CÓDIGO DO MODAL DE COMENTÁRIOS =====
    // Selecionar elementos do modal
    const modal = document.getElementById("commentsModal");
    const commentContent = document.getElementById("commentContent");
    const closeBtn = document.getElementById("closeCommentModal");
    
    // Adicionar listeners para todos os botões de visualização
    document.querySelectorAll(".view-comments-btn").forEach(function(btn) {
        btn.addEventListener("click", function(event) {
            // Evitar propagação do evento para não fechar imediatamente
            event.stopPropagation();
            
            // Obter o comentário do atributo data
            const comments = this.getAttribute("data-comments");
            
            // Preencher o conteúdo do modal
            commentContent.textContent = comments;
            
            // Posicionar o modal próximo ao ícone clicado
            const rect = this.getBoundingClientRect();
            
            // Verificar se há espaço suficiente à direita da tela
            const spaceRight = window.innerWidth - rect.right;
            
            if (spaceRight > 320) {
                // Posicionar à direita do ícone
                modal.style.left = (rect.right + 10) + "px";
            } else {
                // Posicionar à esquerda do ícone
                modal.style.left = (rect.left - 310) + "px";
            }
            
            // Posicionar verticalmente
            const spaceBelow = window.innerHeight - rect.bottom;
            if (spaceBelow < 220 && rect.top > 220) {
                // Colocar acima se não houver espaço suficiente abaixo
                modal.style.top = (rect.top - 210) + "px";
            } else {
                // Posicionar abaixo do ícone
                modal.style.top = rect.bottom + "px";
            }
            
            // Mostrar o modal
            modal.style.display = "block";
        });
    });
    
    // Fechar o modal ao clicar no X
    closeBtn.addEventListener("click", function(event) {
        event.stopPropagation();
        modal.style.display = "none";
    });
    
    // Fechar o modal ao clicar em qualquer lugar fora dele
    document.addEventListener("click", function() {
        modal.style.display = "none";
    });
    
    // Impedir fechamento ao clicar dentro do modal
    modal.addEventListener("click", function(event) {
        event.stopPropagation();
    });

    // ===== CÓDIGO PARA FILTROS NO FRONTEND =====
    
    // Variáveis para armazenar os filtros ativos
    var filtroStatusAtivo = "Todos";
    var filtroTipoTicketAtivo = "Todos";
    
    // Função para filtrar tipo de ticket diretamente na tabela sem recarregar a página
    function filtrarTipoTicketFrontend() {
        // Obter o valor selecionado no filtro
        filtroTipoTicketAtivo = document.getElementById("tipo_ticket_filter").value;
        
        // Aplicar os filtros combinados
        aplicarFiltrosCombinados();
    }
    
    // Função para filtrar status diretamente na tabela sem recarregar a página
    function filtrarStatusFrontend() {
        // Obter o valor selecionado no filtro
        filtroStatusAtivo = document.getElementById("status_filter").value;
        
        // Aplicar os filtros combinados
        aplicarFiltrosCombinados();
    }
    
    // Função para aplicar os filtros combinados
    function aplicarFiltrosCombinados() {
        // Obter todas as linhas da tabela (ignorando a última linha que é o total)
        var tabela = document.querySelector(".tab_cadre_fixehov");
        if (!tabela) return; // Sair se a tabela não existir
        
        var linhas = tabela.querySelectorAll("tbody tr:not(:last-child)");
        var totalVisivel = 0;
        var totalCusto = 0;
        
        // Percorrer todas as linhas da tabela (exceto a última que é o totalizador)
        linhas.forEach(function(linha) {
            // A coluna de tipo de ticket é a 2ª coluna (índice 1, contando a partir de 0)
            var celulaTipoTicket = linha.querySelectorAll("td")[1];
            
            // A coluna de status é a 9ª coluna (índice 8, contando a partir de 0)
            var celulaStatus = linha.querySelectorAll("td")[8];
            
            if (!celulaTipoTicket || !celulaStatus) return;
            
            // Obter o texto do tipo de ticket da célula
            var tipoTicketTexto = celulaTipoTicket.textContent.trim();
            
            // Obter o texto do status da célula (pode estar dentro de um span)
            var statusTexto = "";
            if (celulaStatus.querySelector("span")) {
                statusTexto = celulaStatus.querySelector("span").textContent.trim();
            } else {
                statusTexto = celulaStatus.textContent.trim();
            }
            
            // Determinar se a linha deve ser exibida (combinando os dois filtros)
            var mostrarPorTipoTicket = filtroTipoTicketAtivo === "Todos" || tipoTicketTexto === filtroTipoTicketAtivo;
            var mostrarPorStatus = filtroStatusAtivo === "Todos" || statusTexto === filtroStatusAtivo;
            
            // Só mostra se passar em ambos os filtros
            if (mostrarPorTipoTicket && mostrarPorStatus) {
                linha.style.display = ""; // Mostrar a linha
                totalVisivel++;
                
                // Somar ao total de custo (coluna de custo é a 8ª, índice 7)
                var celulaCusto = linha.querySelectorAll("td")[7];
                if (celulaCusto) {
                    // Extrair apenas os números do formato "R$ X.XXX,XX"
                    var custoTexto = celulaCusto.textContent.trim();
                    // Remover "R$ ", substituir pontos por nada e vírgulas por ponto (para float)
                    var custoNum = parseFloat(
                        custoTexto.replace("R$ ", "")
                                 .replace(/\./g, "")
                                 .replace(",", ".")
                    );
                    
                    if (!isNaN(custoNum)) {
                        totalCusto += custoNum;
                    }
                }
            } else {
                linha.style.display = "none"; // Ocultar a linha
            }
        });
        
        // Atualizar a linha de total na tabela
        atualizarLinhaTotal(totalCusto);
        
        // Atualizar a contagem de registros exibidos
        atualizarContagemRegistros(totalVisivel, linhas.length);
    }

    // Função para atualizar a linha de total na tabela
    function atualizarLinhaTotal(totalCusto) {
        var tabela = document.querySelector(".tab_cadre_fixehov");
        if (!tabela) return;
        
        // A última linha da tabela é a linha de total
        var linhaTotais = tabela.querySelector("tbody tr:last-child");
        if (!linhaTotais) return;
        
        // A célula após o colspan=8 é a que contém o total
        var celulaTotalCusto = linhaTotais.cells[8]; // Usar cells[8] é mais direto e confiável
        
        if (celulaTotalCusto) {
            // Formatar o valor total com separadores de milhares e duas casas decimais
            var totalFormatado = totalCusto.toLocaleString("pt-BR", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            
            celulaTotalCusto.textContent = "R$ " + totalFormatado;
        }
    }

    // Função para atualizar a informação de contagem de registros
    function atualizarContagemRegistros(visivel, total) {
        var pagerInfo = document.querySelector(".pager_infos");
        if (pagerInfo) {
            pagerInfo.textContent = "Exibindo " + visivel + " de " + total + " registros (filtrados no frontend)";
        }
    }

    // Configurar eventos para os filtros
    var filtroStatus = document.getElementById("status_filter");
    if (filtroStatus) {
        filtroStatus.addEventListener("change", filtrarStatusFrontend);
    }
    
    var filtroTipoTicket = document.getElementById("tipo_ticket_filter");
    if (filtroTipoTicket) {
        filtroTipoTicket.addEventListener("change", filtrarTipoTicketFrontend);
    }
    
    // Executar os filtros quando a página carrega (com pequeno atraso)
    setTimeout(aplicarFiltrosCombinados, 500);
    
    // ===== CÓDIGO ORIGINAL PARA LIMPEZA DE FILTROS =====
    // Função para limpar filtros
    document.getElementById("btn-limpar-filtros").addEventListener("click", function() {
        // Redireciona mantendo apenas os parâmetros essenciais do sistema
        let baseUrl = "' . $_SERVER['PHP_SELF'] . '";
        let params = new URLSearchParams();
        
        // Manter apenas parâmetros de sistema necessários
        if (new URLSearchParams(window.location.search).has("itemtype")) {
            params.append("itemtype", "PluginCustosdeslocamentoDeslocamento");
        }
        
        window.location.href = baseUrl + (params.toString() ? "?" + params.toString() : "");
    });

    // Verificar se há algum filtro aplicado para abrir automaticamente o acordeão
    let urlParams = new URLSearchParams(window.location.search);
    let hasFilters = false;
    
    // Verifique se algum dos filtros foi aplicado
    ["entities_id", "ticket_id", "id", "tecnico_id", "origem_id", "destino_id", "status", "tipo_ticket", "tipo_operacao", "start_date", "end_date"].forEach(function(param) {
        if (urlParams.has(param) && urlParams.get(param) !== "") {
            hasFilters = true;
        }
    });

    // Se algum filtro foi aplicado, abra o acordeão automaticamente
    if (hasFilters) {
        var myCollapsible = document.getElementById("collapseFilters");
        if (typeof bootstrap !== "undefined" && bootstrap.Collapse) {
            var bsCollapse = new bootstrap.Collapse(myCollapsible, {
                toggle: true
            });
        } else {
            // Fallback para caso bootstrap não esteja disponível
            myCollapsible.classList.add("show");
        }
    }
    
    // Atualizar automaticamente ao selecionar datas
    document.querySelectorAll("#start_date, #end_date").forEach(function(element) {
        element.addEventListener("change", function() {
            var form = this.closest("form");
            var hiddenInput = document.createElement("input");
            hiddenInput.type = "hidden";
            hiddenInput.name = "start";
            hiddenInput.value = "0";
            form.appendChild(hiddenInput);
            form.submit();
        });
    });
    
    // Atualizar a página quando o limite for alterado
    document.querySelector("#limit").addEventListener("change", function() {
        var form = this.closest("form");
        var hiddenInput = document.createElement("input");
        hiddenInput.type = "hidden";
        hiddenInput.name = "start";
        hiddenInput.value = "0";
        form.appendChild(hiddenInput);
        form.submit();
    });
});
</script>';

/**
 * BLOCO: Funções de Exportação do Relatório
 * Implementa a exportação dos dados nos formatos CSV e PDF
 */

/**
 * Função para exportar o relatório em formato CSV
 * Gera um arquivo CSV com todos os registros filtrados
 */
function exportarRelatorioCSV() {
    global $DB;
    
    // Obter parâmetros de filtro com tratamento adequado
    $start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
    $end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
    $id = isset($_GET['id']) && intval($_GET['id']) > 0 ? intval($_GET['id']) : 0;
    $entities_id = isset($_GET['entities_id']) && intval($_GET['entities_id']) > 0 ? intval($_GET['entities_id']) : 0;
    $tipo_ticket = isset($_GET['tipo_ticket']) && !empty($_GET['tipo_ticket']) ? Toolbox::cleanInputText($_GET['tipo_ticket']) : '';
    $ticket_id = isset($_GET['ticket_id']) && intval($_GET['ticket_id']) > 0 ? intval($_GET['ticket_id']) : 0;
    $tecnico_id = isset($_GET['tecnico_id']) && intval($_GET['tecnico_id']) > 0 ? intval($_GET['tecnico_id']) : 0;
    $origem_id = isset($_GET['origem_id']) && intval($_GET['origem_id']) > 0 ? intval($_GET['origem_id']) : 0;
    $destino_id = isset($_GET['destino_id']) && intval($_GET['destino_id']) > 0 ? intval($_GET['destino_id']) : 0;
    $status = isset($_GET['status']) && !empty($_GET['status']) ? Toolbox::cleanInputText($_GET['status']) : '';
    $tipo_operacao = isset($_GET['tipo_operacao']) && !empty($_GET['tipo_operacao']) ? Toolbox::cleanInputText($_GET['tipo_operacao']) : '';
    
    // Construir consulta base SQL para relatório
    $sql = "SELECT 
        v.id, 
        v.entities_id, 
        v.tipo_ticket, 
        v.ticket_id, 
        v.ticket_titulo, 
        v.data_hora, 
        v.tecnico_id, 
        v.origem_id, 
        v.destino_id, 
        v.custo, 
        v.status, 
        v.comentarios,
        v.date_creation,
        v.date_mod,
        c.tipo_operacao,
        c.saldo_anterior,
        c.saldo_posterior,
        c.observacao
    FROM glpi_plugin_custosdeslocamento_viagens v
    LEFT JOIN glpi_plugin_custosdeslocamento_caixa c ON c.viagem_id = v.id";
    
    // Array de condições SQL com tratamento de escape
    $conditions = [];
    
    // Aplicar filtros com tratamento adequado para evitar SQL injection
    if ($id > 0) {
        $conditions[] = "v.id = " . $DB->escape($id);
    }
    
    if ($entities_id > 0) {
        $conditions[] = "v.entities_id = " . $DB->escape($entities_id);
    }
    
    /*
    if (!empty($tipo_ticket)) {
        $conditions[] = "v.tipo_ticket = '" . $DB->escape($tipo_ticket) . "'";
    }*/
    
    if ($ticket_id > 0) {
        $conditions[] = "v.ticket_id = " . $DB->escape($ticket_id);
    }
    
    if (!empty($start_date)) {
        $conditions[] = "DATE(v.data_hora) >= '" . $DB->escape($start_date) . "'";
    }
    
    if (!empty($end_date)) {
        $conditions[] = "DATE(v.data_hora) <= '" . $DB->escape($end_date) . "'";
    }
    
    if ($tecnico_id > 0) {
        $conditions[] = "v.tecnico_id = " . $DB->escape($tecnico_id);
    }
    
    if ($origem_id > 0) {
        $conditions[] = "v.origem_id = " . $DB->escape($origem_id);
    }
    
    if ($destino_id > 0) {
        $conditions[] = "v.destino_id = " . $DB->escape($destino_id);
    }
    
    if (!empty($status)) {
        $conditions[] = "v.status = '" . $DB->escape($status) . "'";
    }
    
    if (!empty($tipo_operacao)) {
        $conditions[] = "c.tipo_operacao = '" . $DB->escape($tipo_operacao) . "'";
    }
    
    // Adicionar cláusula WHERE se houver condições
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    
    // Ordenação
    $sql .= " ORDER BY v.data_hora DESC";
    
    // Executar a consulta
    $result = $DB->query($sql);
    
    if ($result === false) {
        die("Erro na execução da consulta: " . $DB->error());
    }
    
    // Nome do arquivo
    $filename = 'relatorio_viagens_' . date('Y-m-d') . '.csv';
    
    // Configurar cabeçalhos HTTP para download do arquivo
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Abrir o arquivo CSV para escrita
    $output = fopen('php://output', 'w');
    
    // Adicionar BOM para UTF-8 (ajuda Excel a reconhecer corretamente caracteres especiais)
    fprintf($output, "\xEF\xBB\xBF");
    
    // Cabeçalhos das colunas - ordenados corretamente
    $headers = [
        'Cliente', 
        'Tipo de Ticket', 
        'ID do Ticket', 
        'Título do Ticket',
        'Data/Hora', 
        'Técnico', 
        'Origem', 
        'Destino', 
        'Custo', 
        'Status', 
        'Saldo Anterior',
        'Saldo Posterior',
        'Comentários',
        'Observação Financeira',
        'Data de Criação',
        'Data de Modificação'
    ];
    
    // Escrever cabeçalhos
    fputcsv($output, $headers, ';'); // Usar ponto e vírgula como delimitador para melhor compatibilidade com Excel brasileiro
    
    // Dados
    $total_custo = 0;
    
    while ($row = $DB->fetchAssoc($result)) {
        // Obter os dados para cada coluna, garantindo que todos estejam no formato correto
        $Cliente = Dropdown::getDropdownName('glpi_entities', $row['entities_id']);
        $tecnico = getUserName($row['tecnico_id']);
        $origem = Dropdown::getDropdownName('glpi_entities', $row['origem_id']);
        $destino = Dropdown::getDropdownName('glpi_entities', $row['destino_id']);
        $custo = str_replace('.', ',', number_format($row['custo'], 2, ',', '.')); // Formato brasileiro
        
        // Tratar valores nulos ou vazios para garantir exportação correta
        $tipo_operacao = !empty($row['tipo_operacao']) ? $row['tipo_operacao'] : '';
        $saldo_anterior = !empty($row['saldo_anterior']) ? str_replace('.', ',', number_format($row['saldo_anterior'], 2, ',', '.')) : '';
        $saldo_posterior = !empty($row['saldo_posterior']) ? str_replace('.', ',', number_format($row['saldo_posterior'], 2, ',', '.')) : '';
        $comentarios = !empty($row['comentarios']) ? $row['comentarios'] : '';
        $observacao = !empty($row['observacao']) ? $row['observacao'] : '';
        
        // Formatação de datas
        $data_criacao = !empty($row['date_creation']) ? Html::convDateTime($row['date_creation']) : '';
        $data_mod = !empty($row['date_mod']) ? Html::convDateTime($row['date_mod']) : '';
        
        // Preparar array com todos os dados
        $csv_data = [
            $Cliente,                           // Cliente
            $row['tipo_ticket'],                // Tipo de Ticket
            $row['ticket_id'],                  // ID do Ticket
            $row['ticket_titulo'],              // Título do Ticket
            Html::convDateTime($row['data_hora']), // Data/Hora
            $tecnico,                           // Técnico
            $origem,                            // Origem
            $destino,                           // Destino
            $custo,                             // Custo
            $row['status'],                     // Status
            $saldo_anterior,                    // Saldo Anterior
            $saldo_posterior,                   // Saldo Posterior
            $comentarios,                       // Comentários
            $observacao,                        // Observação Financeira
            $data_criacao,                      // Data de Criação
            $data_mod                           // Data de Modificação
        ];
        
        // Escrever linha no CSV
        fputcsv($output, $csv_data, ';'); // Usar ponto e vírgula como delimitador
        
        $total_custo += $row['custo'];
    }
    
    // Adicionar linha de total
    $linha_total = array_fill(0, count($headers), '');
    $linha_total[0] = 'TOTAL';
    $linha_total[9] = str_replace('.', ',', number_format($total_custo, 2, ',', '.'));
    
    fputcsv($output, $linha_total, ';');
    
    // Fechar o arquivo e encerrar execução
    fclose($output);
    exit;
}

/**
 * Função para exportar o relatório em formato PDF
 * Gera um arquivo PDF com os dados formatados para impressão
 */
function exportarRelatorioPDF() {
    global $DB;
    
    // Verificar se a TCPDF já está incluída pelo GLPI
    if (!class_exists('TCPDF')) {
        include_once(GLPI_ROOT . '/vendor/tecnickcom/tcpdf/tcpdf.php');
    }
    
    // Obter parâmetros de filtro com tratamento adequado
    $start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
    $end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
    $id = isset($_GET['id']) && intval($_GET['id']) > 0 ? intval($_GET['id']) : 0;
    $entities_id = isset($_GET['entities_id']) && intval($_GET['entities_id']) > 0 ? intval($_GET['entities_id']) : 0;
    $tipo_ticket = isset($_GET['tipo_ticket']) && !empty($_GET['tipo_ticket']) ? Toolbox::cleanInputText($_GET['tipo_ticket']) : '';
    $ticket_id = isset($_GET['ticket_id']) && intval($_GET['ticket_id']) > 0 ? intval($_GET['ticket_id']) : 0;
    $tecnico_id = isset($_GET['tecnico_id']) && intval($_GET['tecnico_id']) > 0 ? intval($_GET['tecnico_id']) : 0;
    $origem_id = isset($_GET['origem_id']) && intval($_GET['origem_id']) > 0 ? intval($_GET['origem_id']) : 0;
    $destino_id = isset($_GET['destino_id']) && intval($_GET['destino_id']) > 0 ? intval($_GET['destino_id']) : 0;
    $status = isset($_GET['status']) && !empty($_GET['status']) ? Toolbox::cleanInputText($_GET['status']) : '';
    $tipo_operacao = isset($_GET['tipo_operacao']) && !empty($_GET['tipo_operacao']) ? Toolbox::cleanInputText($_GET['tipo_operacao']) : '';
    
    // Construir consulta base SQL para relatório
    $sql = "SELECT 
        v.id, 
        v.entities_id, 
        v.tipo_ticket, 
        v.ticket_id, 
        v.ticket_titulo, 
        v.data_hora, 
        v.tecnico_id, 
        v.origem_id, 
        v.destino_id, 
        v.custo, 
        v.status, 
        v.comentarios,
        v.date_creation,
        v.date_mod,
        c.tipo_operacao,
        c.saldo_anterior,
        c.saldo_posterior,
        c.observacao
    FROM glpi_plugin_custosdeslocamento_viagens v
    LEFT JOIN glpi_plugin_custosdeslocamento_caixa c ON c.viagem_id = v.id";
    
    // Array de condições SQL com tratamento de escape
    $conditions = [];
    
    // Aplicar filtros com tratamento adequado para evitar SQL injection
    if ($id > 0) {
        $conditions[] = "v.id = " . $DB->escape($id);
    }
    
    if ($entities_id > 0) {
        $conditions[] = "v.entities_id = " . $DB->escape($entities_id);
    }
    
    if (!empty($tipo_ticket)) {
        $conditions[] = "v.tipo_ticket = '" . $DB->escape($tipo_ticket) . "'";
    }
    
    if ($ticket_id > 0) {
        $conditions[] = "v.ticket_id = " . $DB->escape($ticket_id);
    }
    
    if (!empty($start_date)) {
        $conditions[] = "DATE(v.data_hora) >= '" . $DB->escape($start_date) . "'";
    }
    
    if (!empty($end_date)) {
        $conditions[] = "DATE(v.data_hora) <= '" . $DB->escape($end_date) . "'";
    }
    
    if ($tecnico_id > 0) {
        $conditions[] = "v.tecnico_id = " . $DB->escape($tecnico_id);
    }
    
    if ($origem_id > 0) {
        $conditions[] = "v.origem_id = " . $DB->escape($origem_id);
    }
    
    if ($destino_id > 0) {
        $conditions[] = "v.destino_id = " . $DB->escape($destino_id);
    }
    
    if (!empty($status)) {
        $conditions[] = "v.status = '" . $DB->escape($status) . "'";
    }
    
    if (!empty($tipo_operacao)) {
        $conditions[] = "c.tipo_operacao = '" . $DB->escape($tipo_operacao) . "'";
    }
    
    // Adicionar cláusula WHERE se houver condições
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    
    // Ordenação
    $sql .= " ORDER BY v.data_hora DESC";
    
    // Executar a consulta
    $result = $DB->query($sql);
    
    if ($result === false) {
        die("Erro na execução da consulta: " . $DB->error());
    }
    
    // Criar novo objeto PDF
    $pdf = new TCPDF('L', 'mm', 'A3', true, 'UTF-8'); // Usar tamanho A3 para acomodar mais dados
    
    // Configurações do documento
    $pdf->SetCreator('GLPI');
    $pdf->SetAuthor('Plugin Custos de Deslocamento');
    $pdf->SetTitle('Relatório de Viagens e Custos');
    $pdf->SetSubject('Viagens e Custos de Deslocamento');
    $pdf->SetKeywords('GLPI, Viagens, Custos, Deslocamento');
    
    // Remover cabeçalho/rodapé padrão
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Configurar margens (reduzidas para maximizar espaço)
    $pdf->SetMargins(5, 5, 5);
    $pdf->SetAutoPageBreak(true, 5);
    
    // Adicionar página
    $pdf->AddPage();
    
    // Configurar fonte
    $pdf->SetFont('helvetica', 'B', 16);
    
    // Título do relatório
    $pdf->Cell(0, 10, 'Relatório de Viagens e Custos de Deslocamento', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 7, 'Período: ' . date('d/m/Y', strtotime($start_date)) . ' a ' . date('d/m/Y', strtotime($end_date)), 0, 1, 'C');
    $pdf->Ln(3);
    
    // Configurar larguras das colunas - ajustadas para caber adequadamente em A3
    $col_widths = array(
        'Cliente' => 30,
        'tipo_ticket' => 20,
        'ticket_id' => 15,
        'ticket_titulo' => 40,
        'data_hora' => 25,
        'tecnico' => 25,
        'origem' => 30,
        'destino' => 30,
        'custo' => 20,
        'status' => 20,
        'saldo_anterior' => 25,
        'saldo_posterior' => 25,
        'comentarios' => 30,
        'observacao' => 30,
        'data_criacao' => 25,
        'data_mod' => 25
    );
    
    // Cabeçalhos da tabela
    $pdf->SetFillColor(200, 200, 200);
    $pdf->SetTextColor(0);
    $pdf->SetFont('helvetica', 'B', 9);
    
    // Linha de cabeçalho
    $pdf->Cell($col_widths['Cliente'], 7, 'Cliente', 1, 0, 'C', 1);
    $pdf->Cell($col_widths['tipo_ticket'], 7, 'Tipo de Ticket', 1, 0, 'C', 1);
    $pdf->Cell($col_widths['ticket_id'], 7, 'ID Ticket', 1, 0, 'C', 1);
    $pdf->Cell($col_widths['ticket_titulo'], 7, 'Título do Ticket', 1, 0, 'C', 1);
    $pdf->Cell($col_widths['data_hora'], 7, 'Data/Hora', 1, 0, 'C', 1);
    $pdf->Cell($col_widths['tecnico'], 7, 'Técnico', 1, 0, 'C', 1);
    $pdf->Cell($col_widths['origem'], 7, 'Origem', 1, 0, 'C', 1);
    $pdf->Cell($col_widths['destino'], 7, 'Destino', 1, 0, 'C', 1);
    $pdf->Cell($col_widths['custo'], 7, 'Custo', 1, 0, 'C', 1);
    $pdf->Cell($col_widths['status'], 7, 'Status', 1, 0, 'C', 1);
    $pdf->Cell($col_widths['saldo_anterior'], 7, 'Saldo Anterior', 1, 0, 'C', 1);
    $pdf->Cell($col_widths['saldo_posterior'], 7, 'Saldo Posterior', 1, 0, 'C', 1);
    $pdf->Cell($col_widths['comentarios'], 7, 'Comentários', 1, 0, 'C', 1);
    $pdf->Cell($col_widths['observacao'], 7, 'Observação', 1, 1, 'C', 1);
    
    // Dados
    $pdf->SetFont('helvetica', '', 8);
    $total_custo = 0;
    $row_count = 0;
    
    while ($row = $DB->fetchAssoc($result)) {
        // Alternar cor de fundo para melhor legibilidade
        $row_count++;
        if ($row_count % 2 == 0) {
            $pdf->SetFillColor(245, 245, 245);
            $fill = 1;
        } else {
            $pdf->SetFillColor(255, 255, 255);
            $fill = 0;
        }
        
        // Preparar dados para exibição
        $Cliente = Dropdown::getDropdownName('glpi_entities', $row['entities_id']);
        $tipo_ticket = $row['tipo_ticket'];
        $ticket_id = $row['ticket_id'];
        $ticket_titulo = $row['ticket_titulo'];
        $data_hora = Html::convDateTime($row['data_hora']);
        $tecnico = getUserName($row['tecnico_id']);
        $origem = Dropdown::getDropdownName('glpi_entities', $row['origem_id']);
        $destino = Dropdown::getDropdownName('glpi_entities', $row['destino_id']);
        $custo = 'R$ ' . number_format($row['custo'], 2, ',', '.');
        $status = $row['status'];
        $saldo_anterior = !empty($row['saldo_anterior']) ? 'R$ ' . number_format($row['saldo_anterior'], 2, ',', '.') : '';
        $saldo_posterior = !empty($row['saldo_posterior']) ? 'R$ ' . number_format($row['saldo_posterior'], 2, ',', '.') : '';
        $comentarios = !empty($row['comentarios']) ? $row['comentarios'] : '';
        $observacao = !empty($row['observacao']) ? $row['observacao'] : '';
        
        
        // Usar MultiCell para texto longo, mas manter na mesma linha
        $current_y = $pdf->GetY();
        $current_x = $pdf->GetX();
        $pdf->MultiCell($col_widths['Cliente'], 7, $Cliente, 1, 'L', $fill);
        $pdf->SetXY($current_x + $col_widths['Cliente'], $current_y);
        
        $current_x = $pdf->GetX();
        $pdf->MultiCell($col_widths['tipo_ticket'], 7, $tipo_ticket, 1, 'L', $fill);
        $pdf->SetXY($current_x + $col_widths['tipo_ticket'], $current_y);
        
        $current_x = $pdf->GetX();
        $pdf->MultiCell($col_widths['ticket_id'], 7, $ticket_id, 1, 'C', $fill);
        $pdf->SetXY($current_x + $col_widths['ticket_id'], $current_y);
        
        $current_x = $pdf->GetX();
        $pdf->MultiCell($col_widths['ticket_titulo'], 7, $ticket_titulo, 1, 'L', $fill);
        $pdf->SetXY($current_x + $col_widths['ticket_titulo'], $current_y);
        
        $current_x = $pdf->GetX();
        $pdf->MultiCell($col_widths['data_hora'], 7, $data_hora, 1, 'C', $fill);
        $pdf->SetXY($current_x + $col_widths['data_hora'], $current_y);
        
        $current_x = $pdf->GetX();
        $pdf->MultiCell($col_widths['tecnico'], 7, $tecnico, 1, 'L', $fill);
        $pdf->SetXY($current_x + $col_widths['tecnico'], $current_y);
        
        $current_x = $pdf->GetX();
        $pdf->MultiCell($col_widths['origem'], 7, $origem, 1, 'L', $fill);
        $pdf->SetXY($current_x + $col_widths['origem'], $current_y);
        
        $current_x = $pdf->GetX();
        $pdf->MultiCell($col_widths['destino'], 7, $destino, 1, 'L', $fill);
        $pdf->SetXY($current_x + $col_widths['destino'], $current_y);
        
        $current_x = $pdf->GetX();
        $pdf->MultiCell($col_widths['custo'], 7, $custo, 1, 'R', $fill);
        $pdf->SetXY($current_x + $col_widths['custo'], $current_y);
        
        $current_x = $pdf->GetX();
        $pdf->MultiCell($col_widths['status'], 7, $status, 1, 'C', $fill);
        $pdf->SetXY($current_x + $col_widths['status'], $current_y);
        
        $current_x = $pdf->GetX();
        $pdf->MultiCell($col_widths['saldo_anterior'], 7, $saldo_anterior, 1, 'R', $fill);
        $pdf->SetXY($current_x + $col_widths['saldo_anterior'], $current_y);
        
        $current_x = $pdf->GetX();
        $pdf->MultiCell($col_widths['saldo_posterior'], 7, $saldo_posterior, 1, 'R', $fill);
        $pdf->SetXY($current_x + $col_widths['saldo_posterior'], $current_y);
        
        $current_x = $pdf->GetX();
        $pdf->MultiCell($col_widths['comentarios'], 7, $comentarios, 1, 'L', $fill);
        $pdf->SetXY($current_x + $col_widths['comentarios'], $current_y);
        
        $current_x = $pdf->GetX();
        $pdf->MultiCell($col_widths['observacao'], 7, $observacao, 1, 'L', $fill);
        
        // Mover para a próxima linha
        $pdf->Ln();
        
        $total_custo += $row['custo'];
    }
    
    // Linha com o total
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(200, 200, 200);
    
    // Calcular a largura total até a coluna de custo
    $width_before_custo = $col_widths['id'] + $col_widths['Cliente'] + $col_widths['tipo_ticket'] + 
                          $col_widths['ticket_id'] + $col_widths['ticket_titulo'] + $col_widths['data_hora'] + 
                          $col_widths['tecnico'] + $col_widths['origem'] + $col_widths['destino'];
    
    $pdf->Cell($width_before_custo, 7, 'Total Geral:', 1, 0, 'R', 1);
    $pdf->Cell($col_widths['custo'], 7, 'R$ ' . number_format($total_custo, 2, ',', '.'), 1, 0, 'R', 1);
    
    // Calcular a largura total após a coluna de custo
    $width_after_custo = $col_widths['status'] + $col_widths['saldo_anterior'] + 
                         $col_widths['saldo_posterior'] + $col_widths['comentarios'] + $col_widths['observacao'];
    
    $pdf->Cell($width_after_custo, 7, '', 1, 1, 'L', 1);
    
    // Rodapé com informações adicionais
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell(0, 5, 'Relatório gerado em: ' . date('d/m/Y H:i:s'), 0, 1, 'R');
    $pdf->Cell(0, 5, 'Plugin Custos de Deslocamento - GLPI', 0, 1, 'R');
    
    // Finalizar e enviar o PDF
    $pdf->Output('relatorio_viagens_' . date('Y-m-d') . '.pdf', 'D');
    exit;
}

// Fechamento da página
Html::footer();
?>