<?php
// Caminho: plugins/custosdeslocamento/ajax/getComprovantes.php
include('../../../inc/includes.php');

// Verificar se o plugin está ativo
$plugin = new Plugin();
if (!$plugin->isActivated('custosdeslocamento')) {
    http_response_code(404);
    die("Plugin não ativado");
}

// Verificar se um ID de viagem foi fornecido
if (!isset($_GET['viagem_id']) || empty($_GET['viagem_id'])) {
    echo "<div class='alert alert-warning'>ID de viagem não especificado</div>";
    exit;
}

$viagem_id = intval($_GET['viagem_id']);
global $DB, $CFG_GLPI;

// Consultar primeiro na tabela de documentos do GLPI
$query1 = "SELECT d.id, d.filename, d.filepath, d.mime
          FROM glpi_documents d
          INNER JOIN glpi_documents_items di ON d.id = di.documents_id
          WHERE di.items_id = $viagem_id
          AND di.itemtype = 'PluginCustosdeslocamentoViagem'";

$result1 = $DB->query($query1);
$count1 = ($result1) ? $DB->numrows($result1) : 0;

// Consultar também na tabela específica do plugin
$query2 = "SELECT d.id, d.filename, d.filepath, d.mime
          FROM glpi_documents d
          INNER JOIN glpi_plugin_custosdeslocamento_viagens_documents vd ON d.id = vd.documents_id
          WHERE vd.viagens_id = $viagem_id";

$result2 = $DB->query($query2);
$count2 = ($result2) ? $DB->numrows($result2) : 0;

// Verificar se foram encontrados documentos
if ($count1 == 0 && $count2 == 0) {
    echo "<div class='alert alert-info'>Nenhum comprovante encontrado para esta viagem.</div>";
    exit;
}

// Iniciar o layout para os documentos
echo "<div class='row gx-3 gy-3'>";

// Processar resultados da primeira consulta
if ($count1 > 0) {
    while ($doc = $DB->fetchAssoc($result1)) {
        displayDocumentThumbnail($doc, $CFG_GLPI);
    }
}

// Processar resultados da segunda consulta
if ($count2 > 0) {
    while ($doc = $DB->fetchAssoc($result2)) {
        displayDocumentThumbnail($doc, $CFG_GLPI);
    }
}

echo "</div>";

/**
 * Função para exibir a miniatura de um documento
 */
function displayDocumentThumbnail($doc, $CFG_GLPI) {
    $docLink = $CFG_GLPI['root_doc'] . "/front/document.send.php?docid=" . $doc['id'];
    $isImage = strpos($doc['mime'], 'image/') === 0;
    
    echo "<div class='col-md-4'>";
    echo "<div class='card h-100'>";
    
    // Cabeçalho do card com nome do arquivo
    echo "<div class='card-header bg-light'>";
    echo "<h6 class='card-title mb-0 text-truncate' title='" . htmlspecialchars($doc['filename']) . "'>";
    echo htmlspecialchars($doc['filename']);
    echo "</h6>";
    echo "</div>";
    
    // Corpo do card com miniatura
    echo "<div class='card-body d-flex flex-column align-items-center justify-content-center' style='height: 200px;'>";
    
    if ($isImage) {
        // Se for imagem, mostrar miniatura
        echo "<img src='$docLink' class='img-fluid' style='max-height: 150px; object-fit: contain;' alt='" . htmlspecialchars($doc['filename']) . "'>";
    } else {
        // Para outros tipos de arquivo, mostrar ícone
        $iconClass = getDocumentIcon($doc['mime']);
        echo "<i class='$iconClass fa-4x mb-2 text-secondary'></i>";
        echo "<p class='mb-0 text-center'>" . getDocumentTypeLabel($doc['mime']) . "</p>";
    }
    
    echo "</div>";
    
    // Rodapé do card com botão de download
    echo "<div class='card-footer'>";
    echo "<a href='$docLink' target='_blank' class='btn btn-sm btn-primary w-100'>";
    echo "<i class='fas fa-download me-2'></i>Baixar";
    echo "</a>";
    echo "</div>";
    
    echo "</div>"; // Fecha card
    echo "</div>"; // Fecha col
}

/**
 * Função para determinar o ícone com base no tipo MIME
 */
function getDocumentIcon($mime) {
    if (strpos($mime, 'image/') === 0) {
        return 'fas fa-image';
    } elseif (strpos($mime, 'application/pdf') === 0) {
        return 'fas fa-file-pdf';
    } elseif (strpos($mime, 'application/msword') === 0 || strpos($mime, 'application/vnd.openxmlformats-officedocument.wordprocessingml') === 0) {
        return 'fas fa-file-word';
    } elseif (strpos($mime, 'application/vnd.ms-excel') === 0 || strpos($mime, 'application/vnd.openxmlformats-officedocument.spreadsheetml') === 0) {
        return 'fas fa-file-excel';
    } elseif (strpos($mime, 'text/') === 0) {
        return 'fas fa-file-alt';
    } else {
        return 'fas fa-file';
    }
}

/**
 * Função para mostrar o tipo de documento de forma legível
 */
function getDocumentTypeLabel($mime) {
    if (strpos($mime, 'image/') === 0) {
        return "Imagem";
    } elseif (strpos($mime, 'application/pdf') === 0) {
        return "PDF";
    } elseif (strpos($mime, 'application/msword') === 0 || strpos($mime, 'application/vnd.openxmlformats-officedocument.wordprocessingml') === 0) {
        return "Documento Word";
    } elseif (strpos($mime, 'application/vnd.ms-excel') === 0 || strpos($mime, 'application/vnd.openxmlformats-officedocument.spreadsheetml') === 0) {
        return "Planilha Excel";
    } elseif (strpos($mime, 'text/') === 0) {
        return "Arquivo de Texto";
    } else {
        return "Documento";
    }
}