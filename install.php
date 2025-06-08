<?php
/**
 * Install script for Custos de Deslocamento Plugin
 */
function plugin_custosdeslocamento_install() {
    // Chama a função definida no hook.php
    include_once(GLPI_ROOT . '/plugins/custosdeslocamento/hook.php');
    return plugin_custosdeslocamento_install_hook(); // Renomear a função no hook.php
}

/**
 * Uninstall script for Custos de Deslocamento Plugin
 */
function plugin_custosdeslocamento_uninstall() {
    // Chama a função definida no hook.php
    include_once(GLPI_ROOT . '/plugins/custosdeslocamento/hook.php');
    return plugin_custosdeslocamento_uninstall();
}

/**
 * Purge script for Custos de Deslocamento Plugin
 */
function plugin_custosdeslocamento_purge() {
    global $DB;
    
    // Remover tabela de relação viagem-documento
    if ($DB->tableExists('glpi_plugin_custosdeslocamento_viagens_documents')) {
        $query = "DROP TABLE `glpi_plugin_custosdeslocamento_viagens_documents`";
        $DB->query($query);
    }
    
    return true;
}