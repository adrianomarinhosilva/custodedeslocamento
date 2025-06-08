<?php
define('PLUGIN_CUSTOSDESLOCAMENTO_VERSION', '1.0.0');

function plugin_init_custosdeslocamento() {
    global $PLUGIN_HOOKS;
    
    // Array de IDs de perfis permitidos
$allowed_profiles = [33, 4, 172];

// Verifica se o usuário tem um perfil permitido
if (isset($_SESSION['glpiactiveprofile']['id']) && 
    in_array($_SESSION['glpiactiveprofile']['id'], $allowed_profiles)) {
        
        $PLUGIN_HOOKS['csrf_compliant']['custosdeslocamento'] = true;
        
        // Adicionar CSS
        $PLUGIN_HOOKS['add_css']['custosdeslocamento'] = 'css/custosdeslocamento.css';
        
        // Add tab to tickets, changes, problems and projects
        Plugin::registerClass('PluginCustosdeslocamentoViagem', [
            'addtabon' => ['Ticket', 'Change', 'Problem', 'Project']
        ]);
        
        // Habilitar contador nas abas
        $PLUGIN_HOOKS['show_count_on_tabs']['custosdeslocamento'] = true;
        
        // Hook for menu
        $PLUGIN_HOOKS['redefine_menus']['custosdeslocamento'] = 'plugin_custosdeslocamento_redefine_menus';
    }
}

// Function to add the main menu
function plugin_custosdeslocamento_redefine_menus($menu) {
    // Check if user has rights to see the menu
    if (!Session::haveRight('config', READ)) {
        return $menu;
    }
    
    // Iniciar a estrutura do menu
    $menu['custosdeslocamento'] = [
        'title' => 'Gestão',
        'icon'  => 'fa-fw ti ti-chart-bar',
        'types' => [],
        'content' => []
    ];
    
    // Adicionar submenu de relatórios
    $menu['custosdeslocamento']['content']['submenu1'] = [
        'title' => 'Relatórios Uber',
        'icon'  => 'fa-fw ti ti-car',
        'page'  => '/plugins/custosdeslocamento/front/relatorio.php',
    ];
    
    // Adicionar submenu de caixa
    $menu['custosdeslocamento']['content']['submenu2'] = [
        'title' => 'Caixa Uber',
        'icon'  => 'fa-fw ti ti-cash',
        'page'  => '/plugins/custosdeslocamento/front/caixa.php',
    ];
    
    return $menu;
}

function plugin_version_custosdeslocamento() {
    return [
        'name'           => 'Custos de Deslocamento',
        'version'        => PLUGIN_CUSTOSDESLOCAMENTO_VERSION,
        'author'         => 'Adriano Marinho',
        'license'        => 'GPLv3+',
        'homepage'       => '',
        'requirements'   => [
            'glpi' => [
                'min' => '10.0.0',
                'max' => '10.1.0',
            ]
        ]
    ];
}

function plugin_custosdeslocamento_check_prerequisites() {
    return true;
}

function plugin_custosdeslocamento_check_config() {
    return true;
}