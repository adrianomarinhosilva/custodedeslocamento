<?php

if (!defined('GLPI_ROOT')) {
   die("Direct access to this file is not allowed.");
}

class PluginCustosdeslocamentoConfig extends CommonDBTM {
    
    static $rightname = 'plugin_custosdeslocamento_config';
    
    static function getTypeName($nb = 0) {
        return __('Configuração', 'custosdeslocamento');
    }
    
    /**
     * Update the configuration
     * @param array $params The configuration parameters
     * @return bool True on success, false on failure
     */
    function updateConfig($params) {
        global $DB;
        
        if (!$this->getFromDB(1)) {
            // Insert if not exists
            return $this->add([
                'id' => 1,
                'valor_minimo_alerta' => $params['valor_minimo_alerta'],
                'emails_alerta' => $params['emails_alerta'],
                'date_creation' => date('Y-m-d H:i:s'),
                'date_mod' => date('Y-m-d H:i:s')
            ]);
        } else {
            // Update if exists
            return $this->update([
                'id' => 1,
                'valor_minimo_alerta' => $params['valor_minimo_alerta'],
                'emails_alerta' => $params['emails_alerta'],
                'date_mod' => date('Y-m-d H:i:s')
            ]);
        }
    }
}