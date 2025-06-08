<?php

function plugin_custosdeslocamento_install_hook() {
    global $DB;
    
    // Criar diretório para comprovantes
    $comprovantesDir = GLPI_DOC_DIR . '/_files/comprovantes';
    if (!file_exists($comprovantesDir)) {
        mkdir($comprovantesDir, 0755, true);
        
        // Criar arquivo .htaccess para proteger o diretório
        $htaccess = $comprovantesDir . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Order Deny,Allow\nDeny from all\n");
        }
    }

    if (!$DB->tableExists('glpi_plugin_custosdeslocamento_viagens')) {
        $query = "CREATE TABLE `glpi_plugin_custosdeslocamento_viagens` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `entities_id` int(11) NOT NULL, 
            `tipo_ticket` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
            `ticket_id` int(11) NOT NULL,
            `ticket_titulo` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
            `data_hora` datetime NOT NULL,
            `tecnico_id` int(11) NOT NULL,
            `origem_id` int(11) NOT NULL,
            `destino_id` int(11) NOT NULL,
            `custo` decimal(10,2) NOT NULL,
            `status` varchar(50) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'Pendente',
            `comentarios` TEXT COLLATE utf8_unicode_ci NULL,
            `date_creation` datetime DEFAULT NULL,
            `date_mod` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `ticket_id` (`ticket_id`),
            KEY `tecnico_id` (`tecnico_id`),
            KEY `origem_id` (`origem_id`),
            KEY `destino_id` (`destino_id`),
            KEY `entities_id` (`entities_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
        
        $DB->query($query) or die("Error creating glpi_plugin_custosdeslocamento_viagens: " . $DB->error());
    }

    if (!$DB->tableExists('glpi_plugin_custosdeslocamento_caixa')) {
        $query = "CREATE TABLE `glpi_plugin_custosdeslocamento_caixa` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `tipo_operacao` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
            `ticket_id` int(11) DEFAULT NULL,
            `viagem_id` int(11) DEFAULT NULL,
            `valor` decimal(10,2) NOT NULL,
            `saldo_anterior` decimal(10,2) NOT NULL,
            `saldo_posterior` decimal(10,2) NOT NULL,
            `usuario_id` int(11) NOT NULL,
            `data_hora` datetime NOT NULL,
            `observacao` text COLLATE utf8_unicode_ci,
            `date_creation` datetime DEFAULT NULL,
            `date_mod` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `viagem_id` (`viagem_id`),
            KEY `usuario_id` (`usuario_id`),
            KEY `ticket_id` (`ticket_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
        
        $DB->query($query) or die("Error creating glpi_plugin_custosdeslocamento_caixa: " . $DB->error());
    }
    
    if (!$DB->tableExists('glpi_plugin_custosdeslocamento_config')) {
        $query = "CREATE TABLE `glpi_plugin_custosdeslocamento_config` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `valor_minimo_alerta` decimal(10,2) NOT NULL DEFAULT '500.00',
            `emails_alerta` text COLLATE utf8_unicode_ci,
            `date_creation` datetime DEFAULT NULL,
            `date_mod` datetime DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
        $DB->query($query) or die("Error creating glpi_plugin_custosdeslocamento_config: " . $DB->error());
        
        // Insert default config
        $query = "INSERT INTO `glpi_plugin_custosdeslocamento_config` 
                  (`valor_minimo_alerta`, `emails_alerta`, `date_creation`) 
                  VALUES (500.00, '', NOW())";
        $DB->query($query);
    }
    
    if (!$DB->tableExists('glpi_plugin_custosdeslocamento_viagens_documents')) {
        $query = "CREATE TABLE IF NOT EXISTS `glpi_plugin_custosdeslocamento_viagens_documents` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `viagens_id` int(11) NOT NULL DEFAULT 0,
            `documents_id` int(11) NOT NULL DEFAULT 0,
            `date_creation` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unicity` (`viagens_id`, `documents_id`),
            KEY `documents_id` (`documents_id`),
            KEY `viagens_id` (`viagens_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
        
        $DB->query($query) or die("Erro ao criar tabela de relação viagem-documento: " . $DB->error());
    }
    
    // Initial value for the cash balance
    if ($DB->tableExists('glpi_plugin_custosdeslocamento_caixa') && 
        $DB->numrows($DB->request('glpi_plugin_custosdeslocamento_caixa')) == 0) {
        $query = "INSERT INTO `glpi_plugin_custosdeslocamento_caixa` 
                 (`tipo_operacao`, `valor`, `saldo_anterior`, `saldo_posterior`, 
                  `usuario_id`, `data_hora`, `observacao`, `date_creation`) 
                 VALUES ('Inicial', 0.00, 0.00, 0.00, " . 
                 $_SESSION['glpiID'] . ", NOW(), 'Saldo inicial', NOW())";
        $DB->query($query);
    }

    return true;
}

function plugin_custosdeslocamento_uninstall() {
    global $DB;

    $tables = [
        'glpi_plugin_custosdeslocamento_viagens',
        'glpi_plugin_custosdeslocamento_caixa',
        'glpi_plugin_custosdeslocamento_config',
        'glpi_plugin_custosdeslocamento_viagens_documents'
    ];

    foreach ($tables as $table) {
        if ($DB->tableExists($table)) {
            $query = "DROP TABLE `$table`";
            $DB->query($query) or die("Error dropping $table");
        }
    }

    return true;
}