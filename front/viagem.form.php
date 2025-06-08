<?php
include('../../../inc/includes.php');

$plugin = new Plugin();
if (!$plugin->isActivated('custosdeslocamento')) {
   Html::displayNotFoundError();
}


// Caminho para log personalizado
define('DEBUG_LOG_FILE', GLPI_LOG_DIR . '/custosdeslocamento_debug.log');

// Definir o diretório específico para comprovantes
define('COMPROVANTES_DIR', GLPI_DOC_DIR . '/_files/comprovantes');

// Função para registrar logs de debug
function debug_log($data, $title = '') {
    $log_content = '[' . date('Y-m-d H:i:s') . '] ';
    
    if (!empty($title)) {
        $log_content .= $title . ": ";
    }
    
    if (is_array($data) || is_object($data)) {
        $log_content .= print_r($data, true);
    } else {
        $log_content .= $data;
    }
    
    $log_content .= "\n";
    
    file_put_contents(DEBUG_LOG_FILE, $log_content, FILE_APPEND);
}

// Função para garantir que o diretório de comprovantes exista
function garantir_diretorio_comprovantes() {
    if (!file_exists(COMPROVANTES_DIR)) {
        if (!mkdir(COMPROVANTES_DIR, 0755, true)) {
            debug_log("Falha ao criar diretório de comprovantes: " . COMPROVANTES_DIR, 'ERRO');
            return false;
        }
    }
    return true;
}

$viagem = new PluginCustosdeslocamentoViagem();

if (isset($_POST['add'])) {
    debug_log('Iniciando processamento do formulário de viagem', 'INFO');
    debug_log($_POST, 'Dados do POST');
    
    // Verificar se existe saldo disponível antes de tudo
    $caixa = new PluginCustosdeslocamentoCaixa();
    $saldo_atual = $caixa->getSaldo();
    $custo = floatval(str_replace(',', '.', $_POST['custo']));
    
    // Verificar se há saldo disponível
    if ($saldo_atual < $custo) {
        Session::addMessageAfterRedirect(
            __('Não há saldo suficiente no caixa para registrar esta viagem. Saldo atual: R$ ', 'custosdeslocamento') 
            . number_format($saldo_atual, 2, ',', '.'),
            true,
            ERROR
        );
        Html::back();
        exit;
    }
    
    // Verificar campos obrigatórios
    if (empty($_POST['tecnico_id']) || empty($_POST['origem_id']) || 
        empty($_POST['destino_id']) || empty($_POST['custo']) || floatval($_POST['custo']) <= 0) {
        
        Session::addMessageAfterRedirect(
            __('Todos os campos são obrigatórios e o custo deve ser maior que zero', 'custosdeslocamento'),
            true,
            ERROR
        );
        Html::back();
        exit;
    }
    
    // Garantir que o diretório de comprovantes exista
    if (!garantir_diretorio_comprovantes()) {
        Session::addMessageAfterRedirect(
            __('Erro ao preparar o diretório para comprovantes', 'custosdeslocamento'),
            true,
            ERROR
        );
        Html::back();
        exit;
    }
    
    // Capturar os comentários (novo campo)
    $comentarios = isset($_POST['comentarios']) ? $_POST['comentarios'] : '';
    
    // Registrar a viagem
    $params = [
        'tipo_ticket'   => $_POST['tipo_ticket'],
        'ticket_id'     => $_POST['ticket_id'],
        'ticket_titulo' => $_POST['ticket_titulo'],
        'data_hora'     => date('Y-m-d H:i:s'),
        'tecnico_id'    => $_POST['tecnico_id'],
        'origem_id'     => $_POST['origem_id'],
        'destino_id'    => $_POST['destino_id'],
        'custo'         => $custo,
        'status'        => $_POST['status'],
        'comentarios'   => $comentarios // Novo campo de comentários
    ];
    
    debug_log($params, 'Parâmetros da viagem');
    
    $viagem_id = $viagem->registerViagem($params);
    debug_log('Viagem criada com ID: ' . $viagem_id, 'INFO');
    
    if ($viagem_id) {
        global $DB;
        $doc_ids = [];
        
        // Processar os arquivos enviados
        if (isset($_FILES['comprovantes']) && !empty($_FILES['comprovantes']['name'][0])) {
            debug_log($_FILES, 'Arquivos recebidos');
            
            // Definir extensões permitidas
            $allowed_extensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
            
            // Definir tamanho máximo de arquivo (10MB)
            $max_file_size = 10 * 1024 * 1024; // 10MB
            
            for ($i = 0; $i < count($_FILES['comprovantes']['name']); $i++) {
                if (empty($_FILES['comprovantes']['name'][$i])) continue;
                
                $filename = $_FILES['comprovantes']['name'][$i];
                $tmpname = $_FILES['comprovantes']['tmp_name'][$i];
                $filesize = $_FILES['comprovantes']['size'][$i];
                
                // Validar extensão do arquivo
                $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if (!in_array($file_extension, $allowed_extensions)) {
                    debug_log("Extensão de arquivo não permitida: $file_extension para $filename", 'ERRO');
                    Session::addMessageAfterRedirect(
                        sprintf(__('Extensão de arquivo não permitida: %s'), $filename),
                        true,
                        ERROR
                    );
                    continue;
                }
                
                // Validar tamanho do arquivo
                if ($filesize > $max_file_size) {
                    debug_log("Arquivo muito grande: $filename (". ($filesize/1024/1024) ."MB)", 'ERRO');
                    Session::addMessageAfterRedirect(
                        sprintf(__('Arquivo muito grande: %s (máximo 10MB)'), $filename),
                        true,
                        ERROR
                    );
                    continue;
                }
                
                if ($_FILES['comprovantes']['error'][$i] != UPLOAD_ERR_OK) {
                    debug_log("Erro no upload do arquivo $filename: " . $_FILES['comprovantes']['error'][$i], 'ERRO');
                    continue;
                }
                
                debug_log("Processando arquivo: $filename", 'INFO');
                
                // Criar nome de arquivo único para evitar colisões
                $safe_filename = 'comprovante_' . $viagem_id . '_' . date('YmdHis') . '_' . uniqid() . '_' . $filename;
                $filepath = COMPROVANTES_DIR . '/' . $safe_filename;
                
                // Copiar o arquivo para o diretório do GLPI
                $glpi_doc_path = GLPI_DOC_DIR . '/_files/comprovantes/' . $safe_filename;
                if (copy($tmpname, $glpi_doc_path)) {
                    debug_log("Arquivo copiado para: $glpi_doc_path", 'SUCESSO');
                    
                    // Criar o documento no GLPI 
                    $document = new Document();
                    $input = [
                        'name' => 'Comprovante_Viagem_' . $viagem_id . '_' . date('YmdHis') . '_' . $i,
                        'filename' => $filename,
                        'filepath' => '_files/comprovantes/' . $safe_filename,  // Caminho relativo
                        'entities_id' => $_SESSION['glpiactive_entity'],
                        'is_recursive' => 1,
                        'documentcategories_id' => 0,
                        'mime' => mime_content_type($filepath),
                        'date_creation' => date('Y-m-d H:i:s'),
                        'users_id' => Session::getLoginUserID()
                    ];
                    
                    $document->check(-1, CREATE, $input);
                    $newID = $document->add($input);
                    
                    if ($newID > 0) {
                        debug_log("Documento criado com ID: $newID", 'SUCESSO');
                        $doc_ids[] = $newID;
                        
                        // Registrar na tabela de relação do plugin
                        $DB->insert(
                            'glpi_plugin_custosdeslocamento_viagens_documents',
                            [
                                'viagens_id' => $viagem_id,
                                'documents_id' => $newID,
                                'date_creation' => date('Y-m-d H:i:s')
                            ]
                        );
                        
                        debug_log("Documento $newID associado à viagem $viagem_id", 'SUCESSO');
                    } else {
                        debug_log("Falha ao criar documento para $filename", 'ERRO');
                    }
                } else {
                    debug_log("Falha ao copiar $tmpname para $filepath", 'ERRO');
                }
            }
        } else {
            debug_log("Nenhum arquivo enviado", 'INFO');
        }
        
        // Adicionar mensagem de sucesso
        Session::addMessageAfterRedirect(
            __('Viagem registrada com sucesso', 'custosdeslocamento'),
            true,
            INFO
        );
    } else {
        debug_log('Falha ao registrar viagem', 'ERRO');
        Session::addMessageAfterRedirect(
            __('Erro ao registrar a viagem', 'custosdeslocamento'),
            true,
            ERROR
        );
    }
    
    // Redirecionar de volta para a página do ticket
    $itemtype = $_POST['item_type'];
    $item_id = $_POST['ticket_id'];
    
    Html::redirect($CFG_GLPI['root_doc'] . "/front/". strtolower($itemtype) . ".form.php?id=$item_id");
    exit;
}

// Se não está adicionando viagem, redirecionar para a página anterior ou para a página de tickets
if (isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER'])) {
    Html::redirect($_SERVER['HTTP_REFERER']);
} else {
    Html::redirect($CFG_GLPI['root_doc'] . "/front/ticket.php");
}
?>