<?php
class ControllerShippingFreteRapido extends Controller
{
    private $error = array();

    public function install()
    {
        $this->load->model('localisation/language');
        $this->load->model('localisation/order_status');
        $this->load->language('shipping/freterapido');

        // Cria a tabela que relaciona as categorias do OpenCart com as do Frete Rápido
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "category_to_fr_category`
            (
                category_id INT(11) NOT NULL,
                fr_category_id INT(11) NOT NULL,
                CONSTRAINT `PRIMARY` PRIMARY KEY (category_id, fr_category_id)
            );
        ");

        // Cria a tabela de categorias do Frete Rápido
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `fr_category`
            (
              fr_category_id INT(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
              name           VARCHAR(255)        NOT NULL,
              code           SMALLINT(6)         NOT NULL
            );
        ");

        // Limpa os registros da tabela de categorias
        $this->db->query("
            TRUNCATE TABLE fr_category;
        ");

        // Insere novamente as categorias do Frete Rápido
        $this->db->query("
            INSERT INTO fr_category
            (fr_category_id, name, code) VALUES
            (1, 'Abrasivos', 1),
            (2, 'Adubos / Fertilizantes', 2),
            (3, 'Alimentos perecíveis', 3),
            (4, 'Artigos para Pesca', 4),
            (5, 'Auto Peças', 5),
            (6, 'Bebidas / Destilados', 6),
            (7, 'Brindes', 7),
            (8, 'Brinquedos', 8),
            (9, 'Calçados', 9),
            (10, 'CD / DVD / Blu-Ray', 10),
            (11, 'Combustíveis / Óleos', 11),
            (12, 'Confecção', 12),
            (13, 'Cosméticos', 13),
            (14, 'Couro', 14),
            (15, 'Derivados Petróleo', 15),
            (16, 'Descartáveis', 16),
            (17, 'Editorial', 17),
            (18, 'Eletrônicos', 18),
            (19, 'Eletrodomésticos', 19),
            (20, 'Embalagens', 20),
            (21, 'Explosivos / Pirotécnicos', 21),
            (22, 'Medicamentos', 22),
            (23, 'Ferragens', 23),
            (24, 'Ferramentas', 24),
            (25, 'Fibras Ópticas', 25),
            (26, 'Fonográfico', 26),
            (27, 'Fotográfico', 27),
            (28, 'Fraldas / Geriátricas', 28),
            (29, 'Higiene / Limpeza', 29),
            (30, 'Impressos', 30),
            (31, 'Informática / Computadores', 31),
            (32, 'Instrumento Musical', 32),
            (33, 'Livro(s)', 33),
            (34, 'Materiais Escolares', 34),
            (35, 'Materiais Esportivos', 35),
            (36, 'Materiais Frágeis', 36),
            (37, 'Material de Construção', 37),
            (38, 'Material de Irrigação', 38),
            (39, 'Material Elétrico / Lâmpada(s)', 39),
            (40, 'Material Gráfico', 40),
            (41, 'Material Hospitalar', 41),
            (42, 'Material Odontológico', 42),
            (43, 'Material Pet Shop', 43),
            (44, 'Material Veterinário', 44),
            (45, 'Móveis montados', 45),
            (46, 'Moto Peças', 46),
            (47, 'Mudas / Plantas', 47),
            (48, 'Papelaria / Documentos', 48),
            (49, 'Perfumaria', 49),
            (50, 'Material Plástico', 50),
            (51, 'Pneus e Borracharia', 51),
            (52, 'Produtos Cerâmicos', 52),
            (53, 'Produtos Químicos', 53),
            (54, 'Produtos Veterinários', 54),
            (55, 'Revistas', 55),
            (56, 'Sementes', 56),
            (57, 'Suprimentos Agrícolas / Rurais', 57),
            (58, 'Têxtil', 58),
            (59, 'Vacinas', 59),
            (60, 'Vestuário', 60),
            (61, 'Vidros / Frágil', 61),
            (62, 'Cargas refrigeradas/congeladas', 62),
            (63, 'Papelão', 63),
            (64, 'Móveis desmontados', 64),
            (65, 'Sofá', 65),
            (66, 'Colchão', 66),
            (67, 'Travesseiro', 67),
            (68, 'Móveis com peças de vidro', 68),
            (69, 'Acessórios de Airsoft / Paintball', 69),
            (70, 'Acessórios de Pesca', 70),
            (71, 'Simulacro de Arma / Airsoft', 71),
            (72, 'Arquearia', 72),
            (73, 'Acessórios de Arquearia', 73),
            (74, 'Alimentos não perecíveis', 74),
            (75, 'Caixa de embalagem', 75),
            (76, 'TV / Monitores', 76),
            (77, 'Linha Branca', 77),
            (78, 'Vitaminas / Suplementos nutricionais', 78),
            (79, 'Malas / Mochilas', 79),
            (80, 'Máquina / Equipamentos', 80),
            (81, 'Rações / Alimento para Animal', 81),
            (82, 'Artigos para Camping)', 82),
            (83, 'Outros', 999);
        ");

        $row = $this->db->query("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA LIKE '" . DB_DATABASE . "' AND TABLE_NAME LIKE '" . DB_PREFIX . "product' AND COLUMN_NAME = 'manufacturing_deadline' ");

        if ($row->num_rows === 0) {
            // Adiciona a coluna 'manufacturing_deadline' na tabela *_product
            $this->db->query("ALTER TABLE " . DB_PREFIX . "product ADD manufacturing_deadline INT(11) DEFAULT '0' NOT NULL AFTER stock_status_id");
        }

        // Cria a tabela para inserir metadata dos fretes
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "order_meta`
            (
              meta_id INT(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
              order_id INT(11) NOT NULL,
              meta_key VARCHAR(255),
              meta_value LONGTEXT
            );
        ");

        // Insere o status que será usado para a contratação
        $languages = $this->model_localisation_language->getLanguages();
        $new_order_status = array();
        $text_status_awaiting_shipment = $this->language->get('text_status_awaiting_shipment');

        foreach ($languages as $language) {
            $new_order_status['order_status'][$language['language_id']] = array('name' => $text_status_awaiting_shipment);
        }

        $this->model_localisation_order_status->addOrderStatus($new_order_status);
    }

    public function index()
    {
        $this->language->load('shipping/freterapido');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('freterapido', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->redirect($this->url->link('extension/shipping', 'token=' . $this->session->data['token'], 'SSL'));
        }

        $data['heading_title'] = $this->language->get('heading_title');

        $data['text_edit'] = $this->language->get('text_edit');
        $data['text_enabled'] = $this->language->get('text_enabled');
        $data['text_disabled'] = $this->language->get('text_disabled');

        $data['text_results_nofilter'] = $this->language->get('text_results_nofilter');
        $data['text_results_cheaper'] = $this->language->get('text_results_cheaper');
        $data['text_results_faster'] = $this->language->get('text_results_faster');

        $data['text_none'] = $this->language->get('text_none');
        $data['text_yes'] = $this->language->get('text_yes');
        $data['text_no'] = $this->language->get('text_no');
        $data['text_select_all'] = $this->language->get('text_select_all');
        $data['text_unselect_all'] = $this->language->get('text_unselect_all');

        $data['entry_freterapido_token'] = $this->language->get('entry_freterapido_token');
        $data['entry_freterapido_token_code'] = $this->language->get('entry_freterapido_token_code');
        $data['entry_cost'] = $this->language->get('entry_cost');
        $data['entry_status'] = $this->language->get('entry_status');
        $data['entry_sort_order'] = $this->language->get('entry_sort_order');
        $data['entry_cnpj'] = $this->language->get('entry_cnpj');
        $data['entry_results'] = $this->language->get('entry_results');
        $data['entry_limit'] = $this->language->get('entry_limit');
        $data['entry_free_shipping'] = $this->language->get('entry_free_shipping');
        $data['entry_min_value_free_shipping'] = $this->language->get('entry_min_value_free_shipping');
        $data['entry_dimension'] = $this->language->get('entry_dimension');
        $data['entry_length'] = $this->language->get('entry_length');
        $data['entry_width'] = $this->language->get('entry_width');
        $data['entry_height'] = $this->language->get('entry_height');

        $data['help_cnpj'] = $this->language->get('help_cnpj');
        $data['help_freterapido_token'] = $this->language->get('help_freterapido_token');
        $data['help_dimension'] = $this->language->get('help_dimension');
        $data['help_dimension_unit'] = $this->language->get('help_dimension_unit');

        $data['button_save'] = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');

        $data['tab_general'] = $this->language->get('tab_general');

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->error['cnpj'])) {
            $data['error_cnpj'] = $this->error['cnpj'];
        } else {
            $data['error_cnpj'] = '';
        }

        if (isset($this->error['postcode'])) {
            $data['error_postcode'] = $this->error['postcode'];
        } else {
            $data['error_postcode'] = '';
        }

        if (isset($this->error['token'])) {
            $data['error_token'] = $this->error['token'];
        } else {
            $data['error_token'] = '';
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'token=' . $this->session->data['token'], 'SSL'),
            'separator' => false
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_shipping'),
            'href' => $this->url->link('extension/shipping', 'token=' . $this->session->data['token'], 'SSL'),
            'separator' => ' :: '
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('shipping/freterapido', 'token=' . $this->session->data['token'], 'SSL'),
            'separator' => ' :: '
        );

        $data['action'] = $this->url->link('shipping/freterapido', 'token=' . $this->session->data['token'], 'SSL');

        $data['cancel'] = $this->url->link('extension/shipping', 'token=' . $this->session->data['token'], true);

        if (isset($this->request->post['freterapido_status'])) {
            $data['freterapido_status'] = $this->request->post['freterapido_status'];
        } else {
            $data['freterapido_status'] = $this->config->get('freterapido_status');
        }

        if (isset($this->request->post['freterapido_cnpj'])) {
            $data['freterapido_cnpj'] = $this->request->post['freterapido_cnpj'];
        } else {
            $data['freterapido_cnpj'] = $this->config->get('freterapido_cnpj');
        }

        if (isset($this->request->post['freterapido_results'])) {
            $data['freterapido_results'] = $this->request->post['freterapido_results'];
        } else {
            $data['freterapido_results'] = $this->config->get('freterapido_results');
        }

        if (isset($this->request->post['freterapido_limit'])) {
            $data['freterapido_limit'] = $this->request->post['freterapido_limit'];
        } else {
            $data['freterapido_limit'] = $this->config->get('freterapido_limit');
        }

        if (isset($this->request->post['freterapido_free_shipping'])) {
            $data['freterapido_free_shipping'] = $this->request->post['freterapido_free_shipping'];
        } else {
            $data['freterapido_free_shipping'] = $this->config->get('freterapido_free_shipping');
        }

        if (isset($this->request->post['freterapido_min_value_free_shipping'])) {
            $data['freterapido_min_value_free_shipping'] = $this->request->post['freterapido_min_value_free_shipping'];
        } else {
            $data['freterapido_min_value_free_shipping'] = $this->config->get('freterapido_min_value_free_shipping');
        }

        if (isset($this->request->post['freterapido_msg_prazo'])) {
            $data['freterapido_msg_prazo'] = $this->request->post['freterapido_msg_prazo'];
        } else {
            $data['freterapido_msg_prazo'] = $this->config->get('freterapido_msg_prazo');
        }

        if (isset($this->request->post['freterapido_length'])) {
            $data['freterapido_length'] = $this->request->post['freterapido_length'];
        } else {
            $data['freterapido_length'] = $this->config->get('freterapido_length');
        }

        if (isset($this->request->post['freterapido_width'])) {
            $data['freterapido_width'] = $this->request->post['freterapido_width'];
        } else {
            $data['freterapido_width'] = $this->config->get('freterapido_width');
        }

        if (isset($this->request->post['freterapido_height'])) {
            $data['freterapido_height'] = $this->request->post['freterapido_height'];
        } else {
            $data['freterapido_height'] = $this->config->get('freterapido_height');
        }

        if (isset($this->request->post['freterapido_token'])) {
            $data['freterapido_token'] = $this->request->post['freterapido_token'];
        } else {
            $data['freterapido_token'] = $this->config->get('freterapido_token');
        }

        if (isset($this->request->post['freterapido_sort_order'])) {
            $data['freterapido_sort_order'] = $this->request->post['freterapido_sort_order'];
        } else {
            $data['freterapido_sort_order'] = $this->config->get('freterapido_sort_order');
        }

        $this->data = array_merge($this->data, $data);

        $this->template = 'shipping/freterapido.tpl';

        $this->children = array(
            'common/header',
            'common/footer'
        );

        $this->response->setOutput($this->render());
    }

    protected function validate()
    {
        if (!$this->user->hasPermission('modify', 'shipping/freterapido')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!$this->request->post['freterapido_cnpj']) {
            $this->error['cnpj'] = $this->language->get('error_cnpj');
        }

        if (!$this->request->post['freterapido_token']) {
            $this->error['token'] = $this->language->get('error_token');
        }

        return !$this->error;
    }

    public function uninstall()
    {
        $this->load->model('localisation/language');
        $this->load->model('localisation/order_status');
        $this->load->language('shipping/freterapido');

        // Exclui o status usado na contratação
        $statuses = $this->model_localisation_order_status->getOrderStatuses();
        $text_status_awaiting_shipment = $this->language->get('text_status_awaiting_shipment');

        $fr_order_status = array_filter($statuses, function ($status) use ($text_status_awaiting_shipment) {
            return $status['name'] == $text_status_awaiting_shipment;
        });

        if (count($fr_order_status) > 0) {
            $this->model_localisation_order_status->deleteOrderStatus(array_pop($fr_order_status)['order_status_id']);
        }
    }

    public function eventAddOrderHistory($order_id)
    {
        foreach (['config', 'http', 'hire_shipping', 'helpers'] as $file_name) {
            include_once(DIR_APPLICATION . 'model/freterapido/' . $file_name . '.php');
        }

        $this->load->model('sale/order');
        $this->load->model('sale/order_meta');
        $this->load->model('setting/setting');
        $this->load->language('shipping/freterapido');

        $cnpj = fix_zip_code($this->config->get('freterapido_cnpj'));
        $token = $this->config->get('freterapido_token');
        $order_histories = $this->model_sale_order->getOrderHistories($order_id);
        $last_history = array_pop($order_histories);

        if ($last_history['status'] != $this->language->get('text_status_awaiting_shipment')) {
            return;
        }

        $quote = $this->model_sale_order_meta->getMeta($order_id, 'freterapido_quotes');

        // Verifica se é uma oferta própria
        if ($quote === false) {
            return;
        }

        $order = $this->model_sale_order->getOrder($order_id);

        if (empty($order['cpf'])) {
            return;
        }

        $cpf = fix_zip_code($order['cpf']);

        try {
            $hire_shipping = new FreterapidoHireShipping($token);
            $response = $hire_shipping
                ->add_order($order_id)
                ->add_sender(array('cnpj' => $cnpj))
                ->add_receiver(array(
                    'cnpj_cpf' => $cpf,
                    'nome' => "{$order['firstname']} {$order['lastname']}",
                    'email' => $order['email'],
                    'telefone' => $order['telephone'],
                    'endereco' => array(
                        'cep' => fix_zip_code($order['shipping_postcode']),
                        'rua' => $order['shipping_address_1'],
                        'bairro' => $order['shipping_address_2'],
                        'numero' => '',
                    )
                ))
                ->hire_quote($quote['token'], $quote['oferta']);

            $this->model_sale_order_meta->addMeta($order_id, 'freterapido_shippings', array_values($response));
        } catch (UnexpectedValueException $e) {
            $this->log->write($e->getMessage());
        } catch (Exception $e) { }
    }
}
