<?php

if (!defined('GLPI_ROOT')) {
   die("Direct access to this file is not allowed.");
}

class PluginCustosdeslocamentoViagem extends CommonDBTM {
    
    static $rightname = 'plugin_custosdeslocamento_viagem';
    
    static function getTypeName($nb = 0) {
        return _n('Viagem', 'Viagens', $nb, 'custosdeslocamento');
    }
    
    /**
     * Check if there's enough balance for a new travel
     * @param float $cost The cost of the travel
     * @return bool True if there's enough balance, false otherwise
     */
    static function checkSaldo($custo) {
        global $DB;
        
        $result = $DB->request([
            'SELECT' => ['saldo_posterior'],
            'FROM'   => 'glpi_plugin_custosdeslocamento_caixa',
            'ORDER'  => ['id DESC'],
            'LIMIT'  => 1
        ]);
        
        if (count($result) > 0) {
            foreach ($result as $data) {
                return (floatval($data['saldo_posterior']) >= floatval($custo));
            }
        }
        
        return false;
    }
    
    /**
     * Register a new travel expense
     * @param array $params Travel parameters
     * @return bool|int The new ID on success, false on failure
     */
    function registerViagem($params) {
    global $DB;

    // Verificar se há comprovantes anexados
    if (!isset($_FILES['comprovantes']) || empty($_FILES['comprovantes']['name'][0])) {
        Session::addMessageAfterRedirect(
            __('É obrigatório anexar pelo menos um comprovante para registrar a viagem.', 'custosdeslocamento'),
            true,
            ERROR
        );
        return false;
    }
    
    // Garantir que o custo seja um número válido
    $custo = floatval(str_replace(',', '.', $params['custo']));
    
    // Definir status (padrão para Efetuada se não especificado)
    $status = isset($params['status']) ? $params['status'] : 'Efetuada';
    
    // Comentários (novo campo)
    $comentarios = isset($params['comentarios']) ? $params['comentarios'] : '';
    
    // Check if there's enough balance
    if (!self::checkSaldo($custo)) {
        Session::addMessageAfterRedirect(
            __('Não há saldo suficiente no caixa para registrar esta viagem.', 'custosdeslocamento'),
            true,
            ERROR
        );
        return false;
    }

    // Obter a entidade do ticket associado
    $ticket_entity = 0;
    $itemtype = isset($params['tipo_ticket']) ? $params['tipo_ticket'] : 'Ticket';
    
    // Determinar a classe correta com base no tipo de ticket
    $item_class = null;
    switch ($itemtype) {
        case 'Ticket':
            $item_class = 'Ticket';
            break;
        case 'Mudança':
            $item_class = 'Change';
            break;
        case 'Problema':
            $item_class = 'Problem';
            break;
        case 'Projeto':
            $item_class = 'Project';
            break;
        default:
            $item_class = 'Ticket';
    }
    
    // Consultar a entidade do item
    $query = "SELECT entities_id FROM glpi_" . strtolower($item_class) . "s WHERE id = " . intval($params['ticket_id']);
    $result = $DB->query($query);
    
    if ($result && $DB->numrows($result) > 0) {
        $data = $DB->fetchAssoc($result);
        $ticket_entity = $data['entities_id'];
    } else {
        // Fallback para a entidade atual se não encontrar o ticket
        $ticket_entity = $_SESSION['glpiactive_entity'];
    }

    // Get the current balance
    $result = $DB->request([
        'SELECT' => ['saldo_posterior'],
        'FROM'   => 'glpi_plugin_custosdeslocamento_caixa',
        'ORDER'  => ['id DESC'],
        'LIMIT'  => 1
    ]);
    
    $saldo_anterior = 0;
    foreach ($result as $data) {
        $saldo_anterior = floatval($data['saldo_posterior']);
    }
    $saldo_posterior = $saldo_anterior - $custo;
    
    // Start transaction
    $DB->beginTransaction();
    
    // Insert the travel record using direct DB API
    $success = $DB->insert(
        'glpi_plugin_custosdeslocamento_viagens',
        [
            'tipo_ticket'   => $params['tipo_ticket'],
            'ticket_id'     => $params['ticket_id'],
            'ticket_titulo' => $params['ticket_titulo'],
            'data_hora'     => date('Y-m-d H:i:s'),
            'tecnico_id'    => $params['tecnico_id'],
            'origem_id'     => $params['origem_id'],
            'destino_id'    => $params['destino_id'],
            'custo'         => $custo,
            'status'        => $status,
            'comentarios'   => $comentarios, // Salvar os comentários
            'entities_id'   => $ticket_entity, // Usar a entidade do ticket
            'date_creation' => date('Y-m-d H:i:s'),
            'date_mod'      => date('Y-m-d H:i:s')
        ]
    );
    
    if (!$success) {
        $DB->rollBack();
        return false;
    }
    
    $viagem_id = $DB->insertId();
    
    // Register the transaction in the cash table
    $success = $DB->insert(
        'glpi_plugin_custosdeslocamento_caixa',
        [
            'tipo_operacao'   => 'Despesa',
            'ticket_id'       => $params['ticket_id'],
            'viagem_id'       => $viagem_id,
            'valor'           => -$custo, // Negative value since it's an expense
            'saldo_anterior'  => $saldo_anterior,
            'saldo_posterior' => $saldo_posterior,
            'usuario_id'      => Session::getLoginUserID(),
            'data_hora'       => date('Y-m-d H:i:s'),
            'observacao'      => 'Viagem registrada no ticket #' . $params['ticket_id'],
            'date_creation'   => date('Y-m-d H:i:s'),
            'date_mod'        => date('Y-m-d H:i:s')
        ]
    );
    
    if (!$success) {
        $DB->rollBack();
        return false;
    }
    
    // Check if the balance is below the minimum threshold
    $this->checkMinimumBalance($saldo_posterior);
    
    $DB->commit();
    return $viagem_id;
}
    
    /**
     * Check if the balance is below the minimum threshold and send alerts if needed
     * @param float $saldo The current balance
     */
    private function checkMinimumBalance($saldo) {
        global $DB;
        
        $result = $DB->request([
            'FROM'   => 'glpi_plugin_custosdeslocamento_config',
            'LIMIT'  => 1
        ]);
        
        foreach ($result as $config) {
            if ($saldo < $config['valor_minimo_alerta'] && !empty($config['emails_alerta'])) {
                $this->sendAlertEmail($saldo, $config);
            }
        }
    }
    
    /**
     * Send an alert email when the balance is below the minimum threshold
     * @param float $saldo The current balance
     * @param array $config The configuration array
     */
    private function sendAlertEmail($saldo, $config) {
        global $CFG_GLPI;
        
        $emails = explode(',', $config['emails_alerta']);
        
        $subject = '[GLPI] Alerta de Saldo Baixo - Custos de Deslocamento';
        $body = "Olá,\n\n";
        $body .= "O saldo atual do caixa de deslocamento está abaixo do valor mínimo configurado.\n\n";
        $body .= "Saldo atual: R$ " . number_format($saldo, 2, ',', '.') . "\n";
        $body .= "Valor mínimo: R$ " . number_format($config['valor_minimo_alerta'], 2, ',', '.') . "\n\n";
        $body .= "Por favor, adicione fundos ao caixa assim que possível.\n\n";
        $body .= "Atenciosamente,\n";
        $body .= "GLPI - Custos de Deslocamento";
        
        foreach ($emails as $email) {
            if (!empty(trim($email))) {
                NotificationEvent::raiseEvent(
                    'alert',
                    new PluginCustosdeslocamentoViagem(),
                    ['to' => trim($email),
                     'subject' => $subject,
                     'content' => $body]
                );
            }
        }
    }
    
   /**
 * Get the tab name used for item
 * @param object $item the item object
 * @param integer $withtemplate 1 if is a template form
 * @return string name of the tab
 */
function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

    
    switch ($item->getType()) {
        case 'Ticket':
        case 'Change':
        case 'Problem':
        case 'Project':
            $nb = 0;
            if ($_SESSION['glpishow_count_on_tabs']) {
                global $DB;
                
                // Obter o tipo correto para a consulta SQL
                $tipo_ticket = '';
                switch ($item->getType()) {
                    case 'Ticket':
                        $tipo_ticket = 'Ticket';
                        break;
                    case 'Change':
                        $tipo_ticket = 'Mudança';
                        break;
                    case 'Problem':
                        $tipo_ticket = 'Problema';
                        break;
                    case 'Project':
                        $tipo_ticket = 'Projeto';
                        break;
                }
                
                // Contar o número de viagens para este item
                $query = "SELECT COUNT(*) AS total 
                          FROM glpi_plugin_custosdeslocamento_viagens 
                          WHERE ticket_id = " . $item->getID() . " 
                          AND tipo_ticket = '" . $tipo_ticket . "'";
                
                $result = $DB->query($query);
                if ($result && $DB->numrows($result) > 0) {
                    $nb = $DB->result($result, 0, 'total');
                }
            }
            return self::createTabEntry(self::getTypeName(), $nb);
        
        case 'Document':
            $nb = 0;
            if ($_SESSION['glpishow_count_on_tabs']) {
                $nb = countElementsInTable(
                    'glpi_documents_items',
                    [
                        'documents_id' => $item->getID(),
                        'itemtype' => 'PluginCustosdeslocamentoViagem'
                    ]
                );
            }
            return self::createTabEntry(self::getTypeName(Session::getPluralNumber()), $nb);
    }
    return '';
}
    
    /**
     * Display the content of the tab
     * @param object $item the item object
     * @param integer $tabnum number of the tab to display
     * @param integer $withtemplate 1 if is a template form
     * @return boolean
     */
    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
        switch ($item->getType()) {
            case 'Ticket':
                $tipo_ticket = 'Ticket';
                break;
            case 'Change':
                $tipo_ticket = 'Mudança';
                break;
            case 'Problem':
                $tipo_ticket = 'Problema';
                break;
            case 'Project':
                $tipo_ticket = 'Projeto';
                break;
            default:
                return false;
        }
        
        // Show the form for adding new viagens
        self::showViagemForm($item, $tipo_ticket);
        
        // Show existing viagens
        self::showViagensForItem($item, $tipo_ticket);
        
        return true;
    }
    
    /**
     * Permitir documentos anexados a esta classe
     * @return array
     */
    function defineTabs($options = []) {
        $ong = [];
        $this->addDefaultFormTab($ong);
        $this->addStandardTab('Document_Item', $ong, $options);
        return $ong;
    }
    
    /**
     * Relaciona a viagem com os documentos selecionados
     * @param int $viagem_id ID da viagem
     * @param array $document_ids IDs dos documentos
     * @return boolean
     */
    public function relateDocuments($viagem_id, $document_ids) {
        global $DB;
        
        if (empty($document_ids)) {
            return true;
        }
        
        $success = true;
        foreach ($document_ids as $document_id) {
            $doc_item = new Document_Item();
            $input = [
                'documents_id' => $document_id,
                'itemtype' => 'PluginCustosdeslocamentoViagem',
                'items_id' => $viagem_id
            ];
            
            if (!$doc_item->add($input)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Display the travel form with improved styling
     * @param object $item the item object
     * @param string $tipo_ticket the ticket type
     */
    /**
 * Display the travel form with improved styling
 * @param object $item the item object
 * @param string $tipo_ticket the ticket type
 */
static function showViagemForm($item, $tipo_ticket) {
    global $DB, $CFG_GLPI;
    
    $ID = $item->getID();

    // Verificar saldo disponível
    $saldo = 0;
    $result = $DB->request([
        'SELECT' => ['saldo_posterior'],
        'FROM'   => 'glpi_plugin_custosdeslocamento_caixa',
        'ORDER'  => ['id DESC'],
        'LIMIT'  => 1
    ]);

    if (count($result) > 0) {
        $saldo = floatval($result->current()['saldo_posterior']);
    }

    // Buscar o valor mínimo configurado
    $config = new PluginCustosdeslocamentoConfig();
    $config->getFromDB(1);
    $valor_minimo = $config->fields['valor_minimo_alerta'] ?? 100;

    // Definir estilo baseado no saldo
    $estilo_saldo = $saldo < $valor_minimo
        ? "background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;"
        : "background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;";

    // Container principal
    echo "<div class='container-fluid'>";
    
    // Bloco do saldo (mantendo no canto superior direito)
    echo "<div style='text-align: right; margin-bottom: 10px;'>";
    echo "<div style='display: inline-block; $estilo_saldo padding: 10px 15px; border-radius: 5px; font-size: 15px;'>";
    echo "<strong>Saldo Atual: R$ " . number_format($saldo, 2, ',', '.') . "</strong>";
    echo "</div></div>";
    
    // Cabeçalho acordeão no estilo do caixa.class.php
    echo "<div class='card mb-3'>";
    echo "<div class='card-header' style='background-color: #78c2ad; color: white; cursor: pointer; padding: 2px 8px; height: 30px; display: flex; align-items: center;' data-bs-toggle='collapse' data-bs-target='#collapseViagemForm' aria-expanded='true' aria-controls='collapseViagemForm'>";
    echo "<h5 class='m-1'><i class='fas fa-car me-2'></i>" . __('Registrar Nova Viagem', 'custosdeslocamento') . "</h5>";
    echo "</div>";
    
    // Conteúdo do acordeão (inicialmente aberto)
    echo "<div id='collapseViagemForm' class='collapse show'>";
    echo "<div class='card-body' style='background-color: #f8f9fa; padding: 15px;'>";
    
    // Início do formulário
    echo "<form method='post' action='" . Plugin::getWebDir('custosdeslocamento') . "/front/viagem.form.php' enctype='multipart/form-data'>";
    echo "<input type='hidden' name='tipo_ticket' value='$tipo_ticket'>";
    echo "<input type='hidden' name='ticket_id' value='$ID'>";
    echo "<input type='hidden' name='ticket_titulo' value='" . htmlspecialchars($item->getField('name')) . "'>";

    // Linha 1: Origem, Destino e Técnico (mantendo a estrutura de grid)
    echo "<div class='row mb-3'>";
    
    // Origem
    echo "<div class='col-md-4'>";
    echo "<label for='origem_id' class='form-label fw-bold'>Origem:</label>";
    
    $origem_params = [
        'name'           => 'origem_id',
        'entity'         => $_SESSION['glpiactiveentities'],
        'condition'      => [
            'id'            => ['!=', 0],  // Exclui a entidade 0
            'entities_id'   => ['>', 0]    // Apenas entidades que são filhas
        ],
        'display_empty'  => true,
        'display'        => true,
        'specific_tags'  => ['class' => 'form-select']
    ];
    
    Entity::dropdown($origem_params);
    echo "</div>";
    
    // Destino
    echo "<div class='col-md-4'>";
    echo "<label for='destino_id' class='form-label fw-bold'>Destino:</label>";
    
    $destino_params = $origem_params;
    $destino_params['name'] = 'destino_id';
    
    Entity::dropdown($destino_params);
    echo "</div>";
    
    // Técnico
    echo "<div class='col-md-4'>";
    echo "<label for='tecnico_id' class='form-label fw-bold'>Técnico:</label>";
    echo "<select name='tecnico_id' class='form-select'>";
    echo "<option value=''>-----</option>";
    
    $query = "SELECT DISTINCT u.id, CONCAT(u.firstname, ' ', u.realname) AS fullname 
              FROM glpi_users u
              INNER JOIN glpi_profiles_users pu ON u.id = pu.users_id
              WHERE pu.profiles_id IN (35, 38, 28, 172) AND u.is_active = 1
              ORDER BY fullname";
    
    foreach ($DB->request($query) as $data) {
        echo "<option value='{$data['id']}'>{$data['fullname']}</option>";
    }
    
    echo "</select>";
    echo "</div>";
    
    echo "</div>"; // Fim da row 1
    
    // Linha 2: Status, Custo, Comentários e Comprovantes
    echo "<div class='row mb-4'>";
    
    // Status
    echo "<div class='col-md-2'>";
    echo "<label for='status' class='form-label fw-bold'>Status:</label>";
    echo "<select name='status' class='form-select'>";
    echo "<option value='Efetuada'>" . __('Efetuada', 'custosdeslocamento') . "</option>";
    echo "<option value='Cancelada'>" . __('Cancelada', 'custosdeslocamento') . "</option>";
    echo "</select>";
    echo "</div>";
    
    // Custo
    echo "<div class='col-md-2'>";
    echo "<label for='custo' class='form-label fw-bold'>Custo R$:</label>";
    echo "<div class='input-group'>";
    echo "<span class='input-group-text' style='background-color: #e9ecef;'>R$</span>";
    echo "<input type='number' step='0.01' name='custo' required class='form-control'>";
    echo "</div>";
    echo "</div>";
    
    // Comentários
    echo "<div class='col-md-4'>";
    echo "<label for='comentarios' class='form-label fw-bold'>Comentários:</label>";
    echo "<input type='text' name='comentarios' placeholder='Observações sobre a viagem' class='form-control'>";
    echo "</div>";
    
    // Comprovantes
    echo "<div class='col-md-4'>";
    echo "<label for='comprovantes' class='form-label fw-bold'>Comprovantes:</label>";
    echo "<input type='file' name='comprovantes[]' multiple accept='.doc,.docx,.pdf,.png,.jpg,.jpeg' class='form-control' required>";
    echo "</div>";
    
    echo "</div>"; // Fim da row 2
    
    // Linha de botão
    echo "<div class='row'>";
    echo "<div class='col-12 text-center mt-2'>";
    echo "<button type='submit' name='add' class='btn btn-success' style='background-color: #78c2ad; border-color: #78c2ad; padding: 8px 20px; font-weight: bold;'>";
    echo "<i class='fas fa-save me-2'></i>" . __('Registrar Viagem', 'custosdeslocamento');
    echo "</button>";
    echo "</div>";
    echo "</div>";
    
    echo "<input type='hidden' name='item_type' value='{$item->getType()}'>";
    Html::closeForm();
    
    echo "</div>"; // Fim do card-body
    echo "</div>"; // Fim do collapse
    echo "</div>"; // Fim do card
    
    // Script para controlar o comportamento do acordeão
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            var collapseElement = document.getElementById('collapseViagemForm');
            if (collapseElement) {
                // Inicialmente aberto por padrão
                var bsCollapse = new bootstrap.Collapse(collapseElement, {
                    toggle: false
                });
            }
            
            // Adicionar evento de toggle ao clicar no cabeçalho
            document.querySelector('.card-header').addEventListener('click', function() {
                if (bsCollapse) {
                    bsCollapse.toggle();
                }
            });
        });
    </script>";
    
    echo "</div>"; // Fim do container-fluid
}

    
    /**
     * Display the travel list for an item with improved styling
     * @param object $item the item object
     * @param string $tipo_ticket the ticket type
     */
    static function showViagensForItem($item, $tipo_ticket) {
    global $DB, $CFG_GLPI;
    
    // Para evitar a duplicação, vamos verificar se já mostramos esta tabela
    static $already_displayed = false;
    
    // Se já exibimos a tabela uma vez, não exibe de novo
    if ($already_displayed) {
        return;
    }
    
    $already_displayed = true;
    
    $ID = $item->getID();
    
    // Consulta atualizada para incluir entidade e tipo de ticket
    $sql = "SELECT v.*, 
            u.name as tecnico_nome, 
            o.name as origem_nome, 
            d.name as destino_nome,
            e.name as entidade_nome
            FROM glpi_plugin_custosdeslocamento_viagens v
            LEFT JOIN glpi_users u ON v.tecnico_id = u.id
            LEFT JOIN glpi_entities o ON v.origem_id = o.id
            LEFT JOIN glpi_entities d ON v.destino_id = d.id
            LEFT JOIN glpi_entities e ON v.entities_id = e.id
            WHERE v.ticket_id = $ID 
            ORDER BY v.data_hora DESC";
    
    $result = $DB->query($sql);
    $count = $DB->numrows($result);
    
    echo "<h3>Viagens Registradas para o Ticket #$ID</h3>";
    echo "<table class='tab_cadre_fixehov' style='width: 100%;'>";
    
    if ($count > 0) {
        echo "<tr>";
        // Nova ordem das colunas com adição da coluna Comentários
        echo "<th>Data/Hora</th>";
        echo "<th>Cliente</th>"; 
        echo "<th>Tipo de Ticket</th>";
        echo "<th>Origem</th>";
        echo "<th>Destino</th>";
        echo "<th>Status</th>";
        echo "<th>Técnico Envolvido</th>";
        echo "<th>Custo</th>";
        echo "<th>Comentários</th>"; // Nova coluna para Comentários
        echo "<th>Comprovantes</th>";
        echo "</tr>";
        
        $total = 0;
        $i = 0;
        
        while ($data = $DB->fetchAssoc($result)) {
            $rowClass = ($i++ % 2) ? 'tab_bg_1' : 'tab_bg_2';
            echo "<tr class='$rowClass'>";
            
            // Nova ordem das células correspondentes aos cabeçalhos
            echo "<td>" . Html::convDateTime($data['data_hora']) . "</td>";
            
            // Cliente (Entidade)
            echo "<td>" . (!empty($data['entidade_nome']) ? $data['entidade_nome'] : '-') . "</td>";
            
            // Tipo de Ticket
            echo "<td>" . (!empty($data['tipo_ticket']) ? $data['tipo_ticket'] : '-') . "</td>";
            
            // Origem
            echo "<td>" . $data['origem_nome'] . "</td>";
            
            // Destino
            echo "<td>" . $data['destino_nome'] . "</td>";
            
            // Status
            $statusColor = "#000000";
            if ($data['status'] == 'Efetuada') {
                $statusColor = "#28a745";
            } else if ($data['status'] == 'Cancelada') {
                $statusColor = "#dc3545";
            }
            
            echo "<td><span style='color: $statusColor; font-weight: bold;'>" . $data['status'] . "</span></td>";
            
            // Técnico
            echo "<td>" . $data['tecnico_nome'] . "</td>";
            
            // Custo
            echo "<td style='text-align: right;'>R$ " . number_format($data['custo'], 2, ',', '.') . "</td>";
            
            // Comentários (Nova coluna)
            echo "<td>" . (!empty($data['comentarios']) ? htmlspecialchars($data['comentarios']) : '-') . "</td>";
            
            // Comprovantes
            echo "<td>";
            
            // Buscar documentos pela tabela de relação do plugin
            $documentQuery = "
                SELECT d.id, d.filename 
                FROM glpi_documents d
                JOIN glpi_plugin_custosdeslocamento_viagens_documents vd ON d.id = vd.documents_id
                WHERE vd.viagens_id = " . $data['id'];
            
            $documentResult = $DB->query($documentQuery);
            $hasDocuments = $DB->numrows($documentResult) > 0;
            
            if ($hasDocuments) {
                echo "<ul style='list-style: none; padding: 0; margin: 0;'>";
                while ($doc = $DB->fetchAssoc($documentResult)) {
                    $docLink = $CFG_GLPI['root_doc'] . "/front/document.send.php?docid=" . $doc['id'];
                    echo "<li style='margin-bottom: 5px;'>";
                    echo "<a href='$docLink' target='_blank' class='btn btn-sm btn-info'>";
                    echo "<i class='fa fa-file'></i> " . $doc['filename'] . "</a>";
                    echo "</li>";
                }
                echo "</ul>";
            } else {
                // Se não encontrou na tabela específica, busca pela associação padrão do GLPI
                $documentQuery2 = "
                    SELECT d.id, d.filename 
                    FROM glpi_documents d
                    JOIN glpi_documents_items di ON d.id = di.documents_id
                    WHERE di.items_id = " . $data['id'] . " 
                    AND di.itemtype = 'PluginCustosdeslocamentoViagem'";
                
                $documentResult2 = $DB->query($documentQuery2);
                
                if ($DB->numrows($documentResult2) > 0) {
                    echo "<ul style='list-style: none; padding: 0; margin: 0;'>";
                    while ($doc = $DB->fetchAssoc($documentResult2)) {
                        $docLink = $CFG_GLPI['root_doc'] . "/front/document.send.php?docid=" . $doc['id'];
                        echo "<li style='margin-bottom: 5px;'>";
                        echo "<a href='$docLink' target='_blank' class='btn btn-sm btn-info'>";
                        echo "<i class='fa fa-file'></i> " . $doc['filename'] . "</a>";
                        echo "</li>";
                    }
                    echo "</ul>";
                } else {
                    echo "<em>Nenhum comprovante</em>";
                }
            }
            
            echo "</td>";
            echo "</tr>";
            
            $total += floatval($data['custo']);
        }
        
        echo "<tr class='tab_bg_1'>";
        echo "<td colspan='7' style='text-align: right;'><strong>Total:</strong></td>";
        echo "<td style='text-align: right;'><strong>R$ " . number_format($total, 2, ',', '.') . "</strong></td>";
        echo "<td colspan='2'></td>"; // Ajustado o colspan para considerar a nova coluna
        echo "</tr>";
    } else {
        echo "<tr class='tab_bg_1'>";
        echo "<td class='center' colspan='10'>Nenhuma viagem registrada para este ticket</td>"; // Ajustado o colspan para o número correto de colunas
        echo "</tr>";
    }
    
    echo "</table>";
    echo "</div>";
}
}