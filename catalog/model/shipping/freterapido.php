<?php
class ModelShippingFreteRapido extends Model
{
    private $sender;
    private $receiver;
    private $volumes;

    private $manufacturing_deadline = 0;

    /**
     * Será usada pelo produto que não tenha uma categoria do FR definida para ele
     *
     * @var int
     */
    private $default_fr_category = 999;

    function getQuote($address) {
        foreach (['config', 'shipping', 'http', 'helpers'] as $file_name) {
            include_once(DIR_APPLICATION . 'model/freterapido/' . $file_name . '.php');
        }

        $this->load->language('shipping/freterapido');

        $method_data = array();

        if (!$this->validate($address)) {
            return $method_data;
        }

        $this->setup($address);

        $method_data = array(
            'code'       => 'freterapido',
            'title'      => $this->language->get('text_title'),
            'quote'      => array(),
            'sort_order' => $this->config->get('freterapido_sort_order'),
            'error'      => false
        );

        try {
            $shipping = new FreterapidoShipping([
                'token' => $this->config->get('freterapido_token'),
                'codigo_plataforma' => '58b972e6e',
            ]);

            $response = $shipping
                ->add_receiver($this->receiver)
                ->add_sender($this->sender)
                ->set_default_dimensions([
                    'length' => $this->config->get('freterapido_length'),
                    'width'  => $this->config->get('freterapido_width'),
                    'height' => $this->config->get('freterapido_height'),
                ])
                ->add_volumes($this->volumes)
                ->set_filter($this->config->get('freterapido_results'))
                ->set_limit($this->config->get('freterapido_limit'))
                ->get_quote();
        } catch (InvalidArgumentException $invalid_argument) {
            // Quando for erro na autenticação, mostra no "cart"
            $method_data['error'] = $this->language->get('text_error_auth_api');
            return $method_data;
        } catch (UnexpectedValueException $unexpected_value) {
            $this->log->write($unexpected_value->getMessage());
            // Outros erros não faz nada
            return array();
        }

        $quote_data = array();

        $order_by_keys = ['preco_frete', 'prazo_entrega'];

        $has_min_value_free_shipping = $this->cart->getTotal() >= $this->config->get('freterapido_min_value_free_shipping');
        $is_free_shipping_enabled = $this->config->get('freterapido_free_shipping') && $has_min_value_free_shipping;

        // Pega o frete mais barato
        $offers_ordered = array_order_by($response['transportadoras'], $order_by_keys[0], SORT_ASC, $order_by_keys[1], SORT_ASC);
        $free_shipping = array_shift($offers_ordered);

        // Prepara o retorno das ofertas
        foreach ($response['transportadoras'] as $key => $carrier) {
            if ($is_free_shipping_enabled && $carrier['oferta'] == $free_shipping['oferta']) {
                $carrier['preco_frete'] = 0;
                $carrier['custo_frete'] = 0;
            }

            $offer = $this->formatOffer($key, $carrier);
            $offer['meta_data']['token'] = $response['token_oferta'];

            $quote_data[] = $offer;
        }

        return array_merge($method_data, ['quote' => $quote_data]);
    }

    /**
     * Seta os valores da requisição
     *
     * @param $address
     */
    function setup($address) {
        $this->load->model('catalog/product');
        $this->load->model('catalog/category');
        $this->load->model('catalog/fr_category');

        $products = $this->cart->getProducts();

        $this->volumes = $this->getVolumes($products);
        $this->sender = $this->getSender();
        $this->receiver = $this->getReceiver($address);
    }

    /**
     * Valida o endereço do destinatário
     *
     * @param $address
     * @return bool
     */
    function validate($address) {
        if (!isset($address['postcode']) || strlen($this->onlyNumbers($address['postcode'])) !== 8) {
            return false;
        }

        return true;
    }

    /**
     * Formata a oferta retornada pela API para o esperado pelo OpenCart
     *
     * @param $key
     * @param $carrier
     * @return array
     */
    function formatOffer($key, $carrier) {
        $price = $carrier['preco_frete'];

        $text_offer_part_one = $this->language->get('text_offer_part_one');
        $text_offer_part_two_singular = $this->language->get('text_offer_part_two_singular');
        $text_offer_part_two_plural = $this->language->get('text_offer_part_two_plural');

        // Soma o prazo de fabricação ao prazo de entrega da Transportadora
        $deadline = $carrier['prazo_entrega'] + $this->manufacturing_deadline;
        $deadline_text = $deadline == 1 ? $text_offer_part_two_singular : $text_offer_part_two_plural;

        // Coloca o símbolo da moeda do usuário, mas não converte o valor
        $price_formatted = $this->currency->format($price, $this->session->data['currency'], 1);

        if ($price == 0) {
            $price_formatted = 'Frete Grátis';
        }

        $text = "$text_offer_part_one $deadline $deadline_text - $price_formatted";

        $title = $carrier['nome'];

        if (strtolower($title) === 'correios') {
            $title .= " - {$carrier['servico']}";
        }

        return array(
            'code'         => 'freterapido.' . $key,
            'title'        => $title,
            'cost'         => $carrier['custo_frete'],
            'tax_class_id' => 0,
            'text'         => $text,
            'meta_data'    => array('oferta' => $carrier['oferta'])
        );
    }

    /**
     * Cria e preenche os volumes com os dados necessários
     *
     * @param $products
     * @return array
     */
    function getVolumes($products) {
        return array_map(function ($product) {
            // Converte as medidas para o esperado pela API
            $length_class_id = $product['length_class_id'];
            $weight_class_id = $product['weight_class_id'];

            $product_from_db = $this->model_catalog_product->getProduct($product['product_id']);

            $height = $this->convertDimensionToMeters($length_class_id, $product['height']);
            $width = $this->convertDimensionToMeters($length_class_id, $product['width']);
            $length = $this->convertDimensionToMeters($length_class_id, $product['length']);
            $weight = $this->convertWeightToKG($weight_class_id, $product['weight']);

            $volume = array(
                'quantidade'  => $product['quantity'],
                'altura'      => $height,
                'largura'     => $width,
                'comprimento' => $length,
                'peso'        => $weight,
                'valor'       => $product['total'],
                'sku'         => $product_from_db['sku']
            );

            $findFRCategory = function ($category) {
                return $this->findCategory($category['category_id']);
            };

            $notNull = function ($category) {
                return $category !== null;
            };

            $categories = $this->model_catalog_product->getCategories($product['product_id']);
            $fr_categories = array_filter(array_map($findFRCategory, $categories), $notNull);

            $fr_category = ['code' => $this->default_fr_category];

            // Pega a primeira categoria do Frete Rápido encontrada se tiver
            if ($category = array_shift($fr_categories)) {
                $fr_category = $this->model_catalog_fr_category->getCategory($category['category_id']);
            }

            // O prazo de fabricação a ser somado no prazo de entrega é o do produto com maior prazo
            if ($product_from_db['manufacturing_deadline'] > $this->manufacturing_deadline) {
                $this->manufacturing_deadline = $product_from_db['manufacturing_deadline'];
            }

            return array_merge($volume, ['tipo' => $fr_category['code']]);
        }, $products);
    }

    /**
     * Procura a categoria mais próxima do produto que esteja relacionada com alguma do FR
     *
     * @param $category_id
     * @return null|array
     */
    function findCategory($category_id) {
        $category = $this->model_catalog_category->getCategory($category_id);

        if ($fr_category = $this->model_catalog_fr_category->getCategory($category_id)) {
            return $category;
        }

        // Não relacionou nenhuma das categorias vinculadas ao produto com uma categoria do Frete Rápido
        if ($category['parent_id'] == 0) {
            return null;
        }

        return $this->findCategory($category['parent_id']);
    }

    function getSender() {
        return array(
            'cnpj' => $this->onlyNumbers($this->config->get('freterapido_cnpj')),
            'inscricao_estadual' => $this->config->get('freterapido_ie'),
            'endereco' => array(
                'cep' => $this->onlyNumbers($this->config->get('freterapido_postcode'))
            )
        );
    }

    function getReceiver($address) {
        return array(
            'tipo_pessoa' => 1,
            'endereco' => array(
                'cep' => $this->onlyNumbers($address['postcode'])
            )
        );
    }

    /**
     * Retorna apenas os números do $value passado
     *
     * @param $value
     * @return mixed
     */
    function onlyNumbers($value) {
        return preg_replace("/[^0-9]/", '', $value);
    }

    private function convertDimensionToMeters($length_class_id, $dimension) {
        if (!is_numeric($dimension)) {
            return $dimension;
        }

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "length_class lc LEFT JOIN " . DB_PREFIX . "length_class_description lcd ON (lc.length_class_id = lcd.length_class_id) WHERE lc.length_class_id = '" . (int)$length_class_id . "' AND lcd.language_id = '" . (int)$this->config->get('config_language_id') . "'");
        $length_class = $query->row;

        if (isset($length_class['unit']) && $length_class['unit'] == 'mm') {
            $dimension /= 10;
        }

        return $dimension / 100;
    }

    private function convertWeightToKG($weight_class_id, $weight) {
        if (!is_numeric($weight)) {
            return $weight;
        }

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "weight_class wc LEFT JOIN " . DB_PREFIX . "weight_class_description wcd ON (wc.weight_class_id = wcd.weight_class_id) WHERE wc.weight_class_id = '" . (int)$weight_class_id . "' AND wcd.language_id = '" . (int)$this->config->get('config_language_id') . "'");
        $weight_class = $query->row;

        if (isset($weight_class['unit']) && $weight_class['unit'] == 'g') {
            $weight /= 1000;
        }

        return $weight;
    }
}
