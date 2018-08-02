<?php 
if(!defined('_PS_VERSION_'))
    exit;
require_once($_SERVER['DOCUMENT_ROOT'] . '/modules/conector_bled/lib/PSWebServiceLibrary.php');

class conector_bled extends Module{
    public function __construct()
    {    
        $this->name = 'conector_bled'; //nombre del módulo el mismo que la carpeta y la clase.
        $this->tab = 'ShopParameters'; // pestaña en la que se encuentra en el backoffice.
        $this->version = '1.0.0'; //versión del módulo
        $this->author ='Epumer: informatico@barcelonaled.com'; // autor del módulo
        $this->need_instance = 0; //si no necesita cargar la clase en la página módulos,1 si fuese necesario.
        $this->ps_versions_compliancy = array('min' => '1.6.x.x', 'max' => _PS_VERSION_); //las versiones con las que el módulo es compatible.
        $this->bootstrap = true; //si usa bootstrap plantilla responsive.

        parent::__construct(); //llamada al constructor padre.

        $this->displayName = $this->l('Conector BLED'); // Nombre del módulo
        $this->description = $this->l('Módulo de sincronización de barcelonaled a iberianled'); //Descripción del módulo
        $this->confirmUninstall = $this->l('¿Estás seguro de que quieres desinstalar el módulo?'); //mensaje de alerta al desinstalar el módulo.
    }
    public function install()
    {
        return (parent::install()
            && $this->registerHook('actionUpdateQuantity')
            && $this->registerHook('actionProductSave')
            && $this->registerHook('actionFeatureSave')
            && $this->registerHook('actionFeatureValueSave')
            && $this->registerHook('actionAttributeGroupSave')
            && $this->registerHook('actionAttributeSave')
            && $this->registerHook('actionProductDelete')
            && $this->registerHook('actionAttributeGroupDelete')
            && $this->registerHook('actionAttributeDelete')
            && $this->registerHook('actionFeatureDelete')
            && $this->registerHook('actionFeatureValueDelete')
            && $this->installDB()
        );
    }

    public function uninstall()
    {
        $this->_clearCache('*');
        $this->setStatus(false);

        if(!parent::uninstall() 
            || !$this->unregisterHook('actionUpdateQuantity')  
            || !$this->unregisterHook('actionProductSave') 
            || !$this->unregisterHook('actionFeatureSave') 
            || !$this->unregisterHook('actionFeatureValueSave')
            || !$this->unregisterHook('actionAttributeGroupSave')
            || !$this->unregisterHook('actionAttributeSave')
            || !$this->unregisterHook('actionProductDelete')
            || !$this->unregisterHook('actionAttributeGroupDelete')
            || !$this->unregisterHook('actionAttributeDelete')
            || !$this->unregisterHook('actionFeatureDelete')
            || !$this->unregisterHook('actionFeatureValueDelete')
            || !$this->borrar_todo() 
            || !$this->uninstallDB()
        ) {
            return false;
        }

        return true;
    }

    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('submit'.$this->name))
        {
            $external_prestashop_url = (string)Tools::getValue('external_prestashop_url');
            $api_key = (string)Tools::getValue('api_key');
            $lang = Tools::getValue('conector_lang');
            $id_lang = (int)$lang;
            if (!$external_prestashop_url || empty($external_prestashop_url) || !Validate::isGenericName($external_prestashop_url))
            {
                $output .= $this->displayError($this->l('Valor inválido para la url'));
            }
            elseif (!$api_key || empty($api_key) || !Validate::isGenericName($api_key)) 
            {
                $output .= $this->displayError($this->l('Valor inválido para la clave'));
            }
            else
            {
                Configuration::updateValue('external_prestashop_url', $external_prestashop_url);
                Configuration::updateValue('api_key', $api_key);
                Configuration::updateValue('conector_lang', $id_lang);
                try {
                    $ws = new PrestaShopWebservice($external_prestashop_url, $api_key, false);
                    $xml = $ws->get(array('url' => $external_prestashop_url.'/api/products?schema=blank'));
                    try {
                        $this->sincronizarTodo();
                    } catch (Exception $e ) {
                        $output .= $this->displayError($this->l('No se pudo sincronizar todo'));
                    }
                    $output .= $this->displayConfirmation($this->l('Configuración actualizada (Sincronización exitosa)'));
                } catch ( Exception $e ) {
                    $this->log_mensaje("Error> " . $e->getMessage());
                    $output .= $this->displayError($this->l('No se pudo conectar con el prestashop externo'));
                }
            }
        }
        return $output.$this->displayForm().$this->displayList().$this->displayListCombination().$this->displayAttributeList().$this->displayAttributeValueList().$this->displayFeatureList().$this->displayFeatureValueList();
    }

    public function displayForm() {
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
        $langs = Language::getLanguages();
        $options = array();
        foreach ( $langs as $lang ) {
            $options[] = array(
                'id_lang' => $lang['id_lang'],
                'name' => $lang['name']
            );
        }
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Configuración'),
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('URL Prestashop externo'),
                    'name' => 'external_prestashop_url',
                    'size' => 20,
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Clave WebService'),
                    'name' => 'api_key',
                    'size' => 20,
                    'required' => true
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('Idioma'),
                    'name' => 'conector_lang',
                    'options' => array(
                        'query' => $options,
                        'id' => 'id_lang',
                        'name' => 'name'
                    )
                )
            ),
            'submit' => array(
                'title' => $this->l('Guardar'),
                'class' => 'btn btn-default pull-right'
            )
        );

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;        // false -> remove toolbar
        $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.

        $helper->submit_action = 'submit'.$this->name;
        $helper->toolbar_btn = array(
            'save' => array(
                'desc' => $this->l('Guardar'),
                'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
                '&token='.Tools::getAdminTokenLite('AdminModules'),
            ),
            'back' => array(
                'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Atras')
            )
        );

        // Load current value
        $helper->fields_value['external_prestashop_url'] = Configuration::get('external_prestashop_url');
        $helper->fields_value['api_key'] = Configuration::get('api_key');
        $helper->fields_value['conector_lang'] = Configuration::get('conector_lang');

        return $helper->generateForm($fields_form);
    }

    public function displayList() {
        $helper = new HelperList();

        $helper->title = $this->l('Productos sincronizados');
        $helper->table = $this->name;
        $helper->no_link = true;
        $helper->shopLinkType = '';
        $helper->identifier = 'id_conector_bled';
        $values = $this->getValues();
        $helper->tpl_vars = array('show_filters' => false);
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        return $helper->generateList($values, $this->getList());

    }

    public function displayListCombination() {
        $helper = new HelperList();

        $helper->title = $this->l('Combinaciones sincronizadas');
        $helper->table = $this->name;
        $helper->no_link = true;
        $helper->shopLinkType = '';
        $helper->identifier = 'id_conector_bled_comb';
        $values = $this->getValuesCombination();
        $helper->tpl_vars = array('show_filters' => false);
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        return $helper->generateList($values, $this->getListCombination());

    }

    public function displayAttributeList() {
        $helper = new HelperList();

        $helper->title = $this->l('Atributos sincronizados');
        $helper->table = $this->name;
        $helper->no_link = true;
        $helper->shopLinkType = '';
        $helper->identifier = 'id_conector_bled_attr';
        $values = $this->getValuesAttribute();
        $helper->tpl_vars = array('show_filters' => false);
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        return $helper->generateList($values, $this->getListAtributos());

    }

    public function displayAttributeValueList() {
        $helper = new HelperList();

        $helper->title = $this->l('Valor de atributos sincronizados');
        $helper->table = $this->name;
        $helper->no_link = true;
        $helper->shopLinkType = '';
        $helper->identifier = 'id_conector_bled_attrval';
        $values = $this->getValuesAttributeValues();
        $helper->tpl_vars = array('show_filters' => false);
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        return $helper->generateList($values, $this->getListValorAtributos());

    }

    public function displayFeatureList() {
        $helper = new HelperList();

        $helper->title = $this->l('Caracteristicas sincronizadas');
        $helper->table = $this->name;
        $helper->no_link = true;
        $helper->shopLinkType = '';
        $helper->identifier = 'id_conector_bled_feat';
        $values = $this->getValuesFeatures();
        $helper->tpl_vars = array('show_filters' => false);
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        return $helper->generateList($values, $this->getListCaracteristicas());

    }

    public function displayFeatureValueList() {
        $helper = new HelperList();

        $helper->title = $this->l('Valor de caracteristicas sincronizados');
        $helper->table = $this->name;
        $helper->no_link = true;
        $helper->shopLinkType = '';
        $helper->identifier = 'id_conector_bled_feat';
        $values = $this->getValuesFeatureValues();
        $helper->tpl_vars = array('show_filters' => false);
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        return $helper->generateList($values, $this->getListValorCaracteristicas());

    }

    public function hookActionUpdateQuantity($params) {
        if ( $this->getStatus() ) {
            try {
                $product = new Product($params['id_product']);
                if ( $product->hasAttributes() > 0 ) {
                    if ( !empty($params['id_product_attribute'])) {
                        $this->actualizarStockCombinacion($params['id_product'], $params['quantity'], $params['id_product_attribute']);
                    }
                } else {
                    $this->actualizarStock($params['id_product'], $params['quantity']);
                }
            } catch ( Exception $e ) {
                $this->log_mensaje("Error actualizando cantidad de stock> " . $e->getMessage());
            }
        }
    }

    public function hookActionProductSave($params) {
        if ( $this->getStatus() ) {
            $productObj = new Product($params['id_product']);
            $this->actualizarProducto($params['id_product']);
            try {
                $num_combinaciones = $productObj->hasAttributes();
                if ( $num_combinaciones > 0 ) {
                    $this->actualizarCombinacion($params['id_product']);
                    $this->actualizarImagenesCombinacion($params['id_product']);
                }
            } catch ( Exception $e ) {
                $this->log_mensaje("Error guardando producto> ".$e->getMessage());
            }
        }
    }

    public function hookActionFeatureSave($params) {
        if ( $this->getStatus() ) {
            $feature = Feature::getFeature((int)Configuration::getValue('conector_lang'), $params['id_feature']);
            $this->actualizarCaracteristica($feature);
        }
    }

    public function hookActionFeatureValueSave($params) {
        if ( $this->getStatus() ) {
            $sql = 'SELECT `id_feature` FROM `'._DB_PREFIX_.'feature` 
                    WHERE `id_lang`='.(int)Configuration::getValue('conector_lang').' 
                    AND `id_feature_value`='.$params['id_feature_value'].';';
            $id_feature = Db::getInstance()->getValue($sql);
            $feature_value = array(
                        'id_feature' => $id_feature,
                        'id_feature_value' => $params['id_feature_value'],
                        'value' => FeatureValue::selectLang(Feature::getFeatureValueLang($params['id_feature_value']),(int)Configuration::getValue('conector_lang'))
                    );
            $this->actualizarValorCaracteristica($feature_value);
        }
    }

    public function hookActionAttributeGroupSave($params) {
        if ( $this->getStatus() ) {
            $sql = 'SELECT * FROM `'._DB_PREFIX_.'attribute_group`
                    WHERE `id_attribute_group`='.$params['id_attribute_group'].';';
            $attribute_group = Db::getInstance()->query($sql);
            $this->actualizarAtributo($attribute_group->fetch());
        }
    }

    public function hookActionAttributeSave($params) {
        if ( $this->getStatus() ) {
            $sql = 'SELECT * FROM `'._DB_PREFIX_.'attribute`
                    JOIN `'._DB_PREFIX_.'attribute_lang` USING(`id_attribute`)
                    WHERE `id_attribute`='.$params['id_attribute'].';';
            $valor_atributo = Db::getInstance()->query($sql);
            $this->actualizarValorAtributo($valor_atributo->fetch());
        }
    }

    public function hookActionProductDelete($params) {
        if ( $this->getStatus() ) {
            $this->borrar_producto($params['id_product']);
        }
    }

    public function hookActionFeatureDelete($params) {
        if ( $this->getStatus() ) {
            $this->borrar_caracteristica($params['id_feature']);
        }
    }

    public function hookActionFeatureValueDelete($params) {
        if ( $this->getStatus() ) {
            $this->borrar_valor_caracteristica($params['id_feature_value']);
        }
    }

    public function hookActionAttributeGroupDelete($params) {
        if ( $this->getStatus() ) {
            $this->borrar_atributo($params['id_attribute_group']);
        }
    }

    public function hookActionAttributeDelete($params) {
        if ( $this->getStatus() ) {
            $this->borrar_valor_atributo($params['id_attribute']);
        }
    }

    public function sincronizarTodo() {
        $this->reiniciarLogs();
        if ( $this->sincronizarAtributos() && $this->sincronizarCaracteristicas() ) {
            if ( $this->sincronizarValorAtributos() ) {
                if ( $this->sincronizarProductos() ) {
                    if ( !$this->sincronizarStock() ) {
                        $this->log_mensaje("No se pudo sincronizar los stocks");
                    }
                } else {
                    $this->log_mensaje("No se pudo sincronizar los productos");
                }
            } else {
                $this->log_mensaje("No se pudo sincronizar los valores de atributo");
            }
        } else {
            $this->log_mensaje("No se pudo sincronizar los atributos");
        }
        $this->borrar_basura();
        $this->log_mensaje("Sincronización finalizada");
        $this->setStatus(true);
    }

    public function sincronizarStock() {
        $products = $this->getProducts((int)Configuration::get('conector_lang'));
        if ( $products != false ) {
            foreach ($products as $product) {
                try {
                    $productObj = new Product($product['id_product']);
                    if ( $productObj->hasAttributes() > 0 ) {
                        $combinations = $productObj->getAttributeCombinations((int)Configuration::get('conector_lang'));
                        foreach ($combinations as $combination) {
                            $this->actualizarStockCombinacion($product['id_product'], $combination['quantity'], $combination['id_product_attribute']);
                        }
                    } else {
                        $quantity = StockAvailable::getQuantityAvailableByProduct($product['id_product']);
                        $this->actualizarStock($product['id_product'], $quantity);
                    }
                } catch ( Exception $e ) {
                    $this->log_mensaje("Error> " . $e->getMessage() . "\nError_product_id> " . $product['id_product']);
                    return false;
                }
            }
        }
        return true;
    }

    public function sincronizarCaracteristicas() {
        $features = Feature::getFeatures((int)Configuration::get('conector_lang'));
        foreach ( $features as $feature ) {
            try {
                $this->actualizarCaracteristica($feature);
                $feature_values = FeatureValue::getFeatureValuesWithLang((int)Configuration::get('conector_lang'),$feature['id_feature']);
                foreach ( $feature_values as $feature_value ) {
                    try {
                        $this->actualizarValorCaracteristica($feature_value);
                    } catch (Exception $e) {
                        $this->log_mensaje("Error> " . $e->getMessage() . "\nError_feature_value_id> " . $feature_value['id_feature_value']);
                        return false;
                    }
                }
            } catch (Exception $e) {
                $this->log_mensaje("Error> " . $e->getMessage() . "\nError_feature_id> " . $feature['id_feature']);
                return false;
            }
        }
        return true;
    }

    public function sincronizarProductos() {
        $products = $this->getProducts((int)Configuration::get('conector_lang'));
        if ( $products != false ) {
            $products_sincronizados = array();
            foreach ($products as $product) {
                try {
                    $productObj = new Product($product['id_product']);
                    $this->actualizarProducto($product['id_product']);
                    if ( $productObj->hasAttributes() > 0 ) {
                        $this->actualizarCombinacion($product['id_product']);
                        $this->actualizarImagenesCombinacion($product['id_product']);
                    }
                } catch ( Exception $e ) {
                    $this->log_mensaje("Error> " . $e->getMessage() . "\nError_product_id> " . $product['id_product']);
                    return false;
                }
            }
        }
        return true;
    }

    public function sincronizarAtributos() {
        $attributes = AttributeGroup::getAttributesGroups((int)Configuration::get('conector_lang'));
        foreach ( $attributes as $attribute ) {
            try {
                $this->actualizarAtributo($attribute);
            } catch (Exception $e) {
                $this->log_mensaje("Error> " . $e->getMessage() . "\nError_attribute_id> " . $attribute['id_attribute_group']);
                return false;
            }
        }
        return true;
    }

    public function sincronizarValorAtributos() {
        $attribute_values = Attribute::getAttributes((int)Configuration::get('conector_lang'));
        foreach ( $attribute_values as $attribute_value ) {
            try {
                $this->actualizarValorAtributo($attribute_value);
            } catch (Exception $e) {
                $this->log_mensaje("Error> " . $e->getMessage() . "\nError_attribute_value_id> " . $attribute_value['id_attribute']);
                return false;
            }
        }
        return true;
    }

    public function actualizarStock($id_product, $quantity) {
        try {
            $ws = new PrestaShopWebservice( (string)Configuration::get('external_prestashop_url'), (string)Configuration::get('api_key'), false);
            $opt = array('resource' => 'products');
            $opt['id'] = $this->getIdExterno($id_product);
            $xml = $ws->get($opt);
            $resources = $xml->children()->children();
            $id_stock = (int) $resources->associations->stock_availables->stock_available->id;
            $opt = array('resource' => 'stock_availables');
            $opt['id'] = $id_stock;
            $xml = $ws->get($opt);
            $resources = $xml->children()->children();
            $resources->quantity = $quantity;
            $opt['putXml'] = $xml->asXML();
            $xml = $ws->edit($opt);
            Db::getInstance()->update('conector_bled_products', array('quantity' => $quantity), 'id_product='.$id_product );
        } catch (Exception $e) {
            $this->log_mensaje("Error actualizando stock> " . $e->getMessage());
        } 
    }

    public function actualizarStockCombinacion($id_product, $quantity, $id_attribute) {
        try {
            $ws = new PrestaShopWebservice( (string)Configuration::get('external_prestashop_url'), (string)Configuration::get('api_key'), false);
            $opt = array('resource' => 'products');
            $opt['id'] = $this->getIdExterno($id_product);
            $xml = $ws->get($opt);
            $stocks = $xml->children()->children()->associations->stock_availables;
            $id = $stocks->xpath('//stock_available[id_product_attribute=' . $this->getIdExterno($id_attribute, true) . ']/id/text()');
            $id_stock = (int) $id[0];
            $opt = array('resource' => 'stock_availables');
            $opt['id'] = $id_stock;
            $xml = $ws->get($opt);
            $resources = $xml->children()->children();
            $resources->quantity = $quantity;
            $opt['putXml'] = $xml->asXML();
            $xml = $ws->edit($opt);
            Db::getInstance()->update('conector_bled_attributes', array('quantity' => $quantity), 'id_product_attribute='.$id_attribute );
        } catch (Exception $e ) {
            $this->log_mensaje("Error actualizando stock> " . $e->getMessage());
        } 
    }

    public function actualizarProducto($id_product) {
        $ws = new PrestaShopWebservice( (string)Configuration::get('external_prestashop_url'), (string)Configuration::get('api_key'), false);
        $nuevo = false;
        $productObj = new Product($id_product, true, (int)Configuration::get('conector_lang'));
        $id_externo = $this->getIdExterno($id_product); 
        if ($this->exists($id_product, 'id_product', 'products')){
            $nuevo = false;
        } else {
            $nuevo = true;
        }
        if ($nuevo) {
            try {
                $opt = array('resource' => 'products');
                $xml = $ws->get(array('url' => (string) Configuration::get('external_prestashop_url').'/api/products?schema=blank'));
                $resources = $xml->children()->children();

                unset($resources->manufacturer_name);
                unset($resources->quantity);

                $resources->id_shop_default = 1;
                $resources->name->language[0] = $productObj->name;
                $resources->description->language[0] = $productObj->description;
                if ( empty($productObj->price) ) {
                    $resources->price = 0.00;
                } else {
                    $resources->price = $productObj->price;
                }
                $resources->ean13 = $productObj->ean13;
                $resources->description_short->language[0] = $productObj->description_short;
                $resources->meta_description->language[0] = $productObj->meta_description;
                $resources->meta_keywords->language[0] = $productObj->meta_keywords;
                $resources->meta_title->language[0] = $productObj->meta_title;
                if ( $productObj->price > 0 && $productObj->active == 1) {
                    $resources->active = $productObj->active;
                } else {
                    $resources->active = 0;
                }
                $resources->state = 1;
                $resources->id_tax_rules_group = 53;
                $resources->reference = $productObj->reference;
                $contador_imagenes = 0;

                foreach ( $productObj->getImages((int)Configuration::get('conector_lang')) as $image ) {
                    $resources->associations->images->image[$contador_imagenes]->id = $image['id_image'];
                    $resources->associations->images->addChild('image');
                    $contador_imagenes = $contador_imagenes + 1;
                    $resources->associations->images->image[$contador_imagenes]->addChild('id');
                }

                $contador_caracteristicas = 0;
                foreach ( $productObj->getFeatures() as $feature ) {
                    $id_feature_e = $this->getIdExternoCaracteristica($feature['id_feature']);
                    $id_feature_value_e = $this->getIdExternoCaracteristica($feature['id_feature_value'], true);
                    if ( !empty($id_feature_e) && !empty($id_feature_value_e) ) {
                        $resources->associations->product_features->product_feature[$contador_caracteristicas]->id = $id_feature_e;
                        $resources->associations->product_features->product_feature[$contador_caracteristicas]->id_feature_value = $id_feature_value_e;
                        $resources->associations->product_features->addChild('product_feature');
                        $contador_caracteristicas = $contador_caracteristicas + 1;
                        $resources->associations->product_features->product_feature[$contador_caracteristicas]->addChild('id');
                        $resources->associations->product_features->product_feature[$contador_caracteristicas]->addChild('id_feature_value');
                    }
                }

                $categorias = $this->getCategorias($productObj);
                if ( $categorias != false ) {
                    $contador_categorias = 0;
                    $defecto = false;
                    foreach ( $categorias as $categoria ) {
                        if ( $categoria != '' ) {
                            if ( !$defecto ) {
                                $resources->id_category_default = $categoria;
                                $defecto = true;
                            }
                            $resources->associations->categories->category[$contador_categorias]->id = $categoria;
                            $resources->associations->categories->addChild('category');
                            $contador_categorias = $contador_categorias + 1;
                            $resources->associations->categories->category[$contador_categorias]->addChild('id');
                        }
                    }
                }

                $resources->id = '';

                $opt['postXml'] = $xml->asXML();
                $respuesta = $ws->add($opt);

                $resources = $respuesta->children()->children();
                $id_externa = $resources->id;
                $this->insertarEnDb($id_product, $productObj->reference, $id_externa);
                $this->actualizarImagenesProducto($id_product);
                $this->log_response("<success>\n\t<mensaje>Éxito añadiendo producto: ".$respuesta->asXML()."</mensaje>\n\t<id>".$id_product."</id>\n\t<xml>".$xml->asXML()."</xml>\n</success>","product_succes");
            } catch (Exception $e) {
                $this->log_mensaje("Error añadiendo producto> " . $id_product . ">" .  $e->getMessage() . $e->getTraceAsString());
                try {
                    $this->log_response("<error>\n\t<mensaje>Error añadiendo producto: ".$e->getTraceAsString()."</mensaje>\n\t<id>".$id_product."</id>\n\t<xml>".$xml->asXML()."</xml>\n</error>","product_failure");
                } catch ( Exception $d ) {

                }
            }
        } else {
            try {
                $opt = array('resource' => 'products');
                $opt['id'] = $id_externo;
                $xml = $ws->get($opt);
                $resources = $xml->children()->children();

                unset($resources->manufacturer_name);
                unset($resources->quantity);

                $resources->id_shop_default = 1;
                $resources->name->language[0] = $productObj->name;
                $resources->description->language[0] = $productObj->description;
                if ( empty($productObj->price) ) {
                    $resources->price = 0.00;
                } else {
                    $resources->price = $productObj->price;
                }
                $resources->ean13 = $productObj->ean13;
                $resources->description_short->language[0] = $productObj->description_short;
                $resources->meta_description->language[0] = $productObj->meta_description;
                $resources->meta_keywords->language[0] = $productObj->meta_keywords;
                $resources->meta_title->language[0] = $productObj->meta_title;
                $resources->state = 1;
                if ( $productObj->price > 0 && $productObj->active == 1 ) {
                    $resources->active = $productObj->active;
                } else {
                    $resources->active = 0;
                }
                $resources->reference = $productObj->reference;

                $image_xml = 
                            "<image>
                                <id></id>
                            </image>";

                $resources->associations->images = simplexml_load_string($image_xml);
                $contador_imagenes = 0;

                foreach ( $productObj->getImages((int)Configuration::get('conector_lang')) as $image ) {
                    $resources->associations->images->image[$contador_imagenes]->id = $image['id_image'];
                    $resources->associations->images->addChild('image');
                    $contador_imagenes = $contador_imagenes + 1;
                    $resources->associations->images->image[$contador_imagenes]->addChild('id');
                }

                $features_xml = 
                                "<product_feature>
                                    <id></id>
                                    <id_feature_value></id_feature_value>
                                </product_feature>";
                $resources->associations->product_features = simplexml_load_string($features_xml);

                $contador_caracteristicas = 0;
                foreach ( $productObj->getFeatures() as $feature ) {
                    $id_feature_e = $this->getIdExternoCaracteristica($feature['id_feature']);
                    $id_feature_value_e = $this->getIdExternoCaracteristica($feature['id_feature_value'], true);
                    if ( !empty($id_feature_e) && !empty($id_feature_value_e)) {
                        $resources->associations->product_features->product_feature[$contador_caracteristicas]->id = $id_feature_e;
                        $resources->associations->product_features->product_feature[$contador_caracteristicas]->id_feature_value = $id_feature_value_e;
                        $resources->associations->product_features->addChild('product_feature');
                        $contador_caracteristicas = $contador_caracteristicas + 1;
                        $resources->associations->product_features->product_feature[$contador_caracteristicas]->addChild('id');
                        $resources->associations->product_features->product_feature[$contador_caracteristicas]->addChild('id_feature_value');
                    }
                }

                $opt['putXml'] = $xml->asXML();
                $respuesta = $ws->edit($opt);
                $this->actualizarImagenesProducto($id_product);
                $this->log_response("<success>\n\t<mensaje>Éxito actualizando producto: ".$respuesta->asXML()."</mensaje>\n\t<id>".$id_product."</id>\n\t<xml>".$xml->asXML()."</xml>\n</success>","product_succes");
            } catch (Exception $e) {
                try {
                    $this->log_response("<error>\n\t<mensaje>Error actualizando producto: ".$e->getTraceAsString()."</mensaje>\n\t<id>".$id_product."</id>\n\t<xml>".$xml->asXML()."</xml>\n</error>","product_failure");
                } catch ( Exception $d ) {

                }
                $this->log_mensaje("Error actualizando producto> " . $id_product . ">" .  $e->getMessage() . $e->getTraceAsString());
            } 
        }
    }

    public function actualizarCombinacion($id_product) {
        $nuevo = false;
        $productObj = new Product($id_product, true, (int)Configuration::get('conector_lang'));
        $combinaciones = $productObj->getAttributeCombinations((int)Configuration::get('conector_lang'));
        foreach ( $combinaciones as $combinacion ) {
            $ws = new PrestaShopWebservice( (string)Configuration::get('external_prestashop_url'), (string)Configuration::get('api_key'), false);
            $id_externo = $this->getIdExterno($combinacion['id_product_attribute'], true);
            if ($this->exists($combinacion['id_product_attribute'], 'id_product_attribute', 'product_attributes')){
                $nuevo = false;
            } else {
                $nuevo = true;
            }
            if ($nuevo) {
                try {
                    $opt = array('resource' => 'combinations');
                    $xml = $ws->get(array('url' => (string) Configuration::get('external_prestashop_url').'/api/combinations?schema=blank'));
                    $resources = $xml->children()->children();

                    unset($resources->manufacturer_name);
                    unset($resources->quantity);
                    $resources->id_product = $this->getIdExterno($id_product);
                    if ( empty($combinacion['price']) ) {
                        $resources->price = 0.00;
                    } else {
                        $resources->price = $combinacion['price'];
                    }
                    $resources->ean13 = $combinacion['ean13'];
                    $resources->reference = $combinacion['reference'];
                    $resources->associations->product_option_values->product_option_value->id = $this->getIdExternoAtributo($combinacion['id_attribute'], true);
                    $resources->minimal_quantity = 0;
                    $resources->id = '';

                    $opt['postXml'] = $xml->asXML();
                    $respuesta = $ws->add($opt);
                    $resources = $xml->children()->children();
                    $id_externa = $resources->id;

                    $this->insertarEnDb($combinacion['id_product_attribute'], $productObj->reference, $id_externa, true);
                    $this->log_response("<error>\n\t<mensaje>Éxito añadiendo combinacion: ".$respuesta->asXML()."</mensaje>\n\t<id>".$id_product."</id>\n\t<xml>".$xml->asXML()."</xml>\n</error>","combination_success");
                } catch (Exception $e) {
                    try {
                        $this->log_response("<error>\n\t<mensaje>Error añadiendo combinacion: ".$e->getTraceAsString()."</mensaje>\n\t<id>".$id_product."</id>\n\t<xml>".$xml->asXML()."</xml>\n</error>","combination_failure");
                    } catch ( Exception $d ) {

                    }
                    $this->log_mensaje("Error añadiendo combinación> ". $id_product . ">" . $e->getMessage());
                }
            } else {
                try {
                    $opt = array('resource' => 'combinations');
                    $opt['id'] = $id_externo;
                    $xml = $ws->get($opt);
                    $resources = $xml->children()->children();
                    unset($resources->manufacturer_name);
                    unset($resources->quantity);
                    if ($resources->associations->product_option_values->product_option_value->id != $this->getIdExternoAtributo($combinacion['id_attribute'], true)) {
                        $resources->associations->product_option_values->addChild('product_option_value');
                        $product_option_value = $resources->xpath('//product_option_value[last()]');
                        $product_option_value[0]->addChild('id');
                        $product_option_value[0]->id = $this->getIdExternoAtributo($combinacion['id_attribute'], true);
                    }
                    if ( empty($combinacion['price']) ) {
                        $resources->price = 0.00;
                    } else {
                        $resources->price = $combinacion['price'];
                    }
                    $resources->ean13 = $combinacion['ean13'];
                    $resources->reference = $combinacion['reference'];
                    $opt['putXml'] = $xml->asXML();
                    $respuesta = $ws->edit($opt);
                    $this->log_response("<error>\n\t<mensaje>Éxito actualizando combinacion: ".$respuesta->asXML()."</mensaje>\n\t<id>".$id_product."</id>\n\t<xml>".$xml->asXML()."</xml>\n</error>","combination_success");
                } catch (Exception $e) {
                    try {
                        $this->log_response("<error>\n\t<mensaje>Error actualizando combinacion: ".$e->getTraceAsString()."</mensaje>\n\t<id>".$id_product."</id>\n\t<xml>".$xml->asXML()."</xml>\n</error>","combination_failure");
                    } catch ( Exception $d ) {

                    }
                    $this->log_mensaje("Error actualizando combinación> " . $id_product . ">" . $e->getTraceAsString());
                }
            }
        }
    }

    public function getCategorias($product) {
        $nombre_archivo = $_SERVER['DOCUMENT_ROOT'] . '/modules/conector_bled/lib/lista_categorias.csv';
        $archivo_categorias = fopen($nombre_archivo,'r');
        $contenido = fread($archivo_categorias, filesize($nombre_archivo));
        $lineas = explode("\n",$contenido);
        $categorias = array();
        foreach ($lineas as $linea) {
            $campos = explode(";",$linea);
            $categorias[$campos[0]] = array( $campos[1], $campos[2] );
        }
        if ( $product->hasAttributes() > 0 ) {
            $combinaciones = $product->getAttributeCombinations((int)Configuration::get('conector_lang'));
            foreach ( $combinaciones as $combinacion ) {
                try {
                    $categorias_combinacion = $categorias[$combinacion['reference']];
                    if ( !empty($categorias_combinacion)) {
                        return $categorias_combinacion;    
                    } else {
                        return false;
                    }
                } catch ( Exception $e ) {
                    $this->log_mensaje('Error categorizando combinacion>' . $e-getMessage() . "\n");
                }
            }
        } else {
            try {
                $categorias_producto = $categorias[$product->reference];
                if ( !empty($categorias_producto) ) {
                    return $categorias_producto;
                } else {
                    return false;
                }
            } catch ( Exception $e ) {
                $this->log_mensaje('Error categorizando>' . $e-getMessage() . "\n");
                return false;
            }
        }
        return false;
    }

    public function actualizarImagenesProducto($id) {
        try {
            if ( Image::hasImages( (int)Configuration::get('conector_lang'), $id ) ) {
                $imagenes = Image::getImages( (int)Configuration::get('conector_lang'), $id );
                if ( !isset($imagenes['id_image']) ) {
                    foreach ( $imagenes as $imagen ) {
                        if ( !$this->imageExists($imagen['id_image']) ) {
                            try {
                                $id_externo = $this->subirImagen($imagen['id_image'], $id);
                                $this->insertarImagenesEnDb($imagen['id_image'], $id_externo);
                            } catch ( Exception $e ) {
                                $this->log_mensaje("Error actualizando imagenes del producto> ".$imagen['id_image'].">" . $e->getMessage());
                            }
                        }
                    }
                } else {
                    if ( !$this->imageExists($imagen['id_image']) ) {
                        try {
                            $this->subirImagen($imagenes['id_image'],$id,true);
                        } catch ( Exception $e ) {
                                $this->log_mensaje("Error actualizando imagen del producto> ".$imagenes['id_image'].">" . $e->getMessage());
                        }       
                    }
                }
            }
        } catch ( Exception $e ) {
            $this->log_mensaje("Error actualizando imagenes del producto> " . $e->getTraceAsString());
        }
    }

    public function actualizarImagenesCombinacion($id_product) {
        try {
            $productObj = new Product($id_product, true, (int)Configuration::get('conector_lang'));
            $combinaciones = $productObj->getAttributeCombinations((int)Configuration::get('conector_lang'));
            foreach( $combinaciones as $combinacion ) {
                if ( Image::hasImages( (int)Configuration::get('conector_lang'), $id_product, $combinacion['id_product_attribute'] ) ) {
                    $imagenes = Image::getImages( (int)Configuration::get('conector_lang'), $id_product, $combinacion['id_product_attribute'] );
                    if (!isset($imagenes['id_image'])) {
                        foreach ( $imagenes as $imagen ) {
                            if (!$this->imageExists($imagen['id_image'])) {
                                try {
                                    $id_externo = $this->subirImagen($imagen['id_image'], $id_product, true);
                                    $this->insertarImagenesEnDb($imagen['id_image'], $id_externo);
                                } catch ( Exception $e ) {
                                    $this->log_mensaje("Error actualizando imagenes de la combinación> ".$imagen['id_image'].">" . $e->getMessage());
                                }
                            }
                        }
                    } else {
                        if  (!$this->imageExists($imagen['id_image'])) {
                            try {
                                $this->subirImagen($imagenes['id_image'],$id_product,true);
                                foreach ( ImageType::getImagesTypes() as $imageType ) { 
                                    $this->subirImagen($imagenes['id_image'], $id_product, true, '-'.$imageType['name']);
                                }
                            } catch ( Exception $e ) {
                                    $this->log_mensaje("Error actualizando imagen de la combinación> " .$imagenes['id_image'].">". $e->getMessage());
                            }       
                        }
                    }
                }
            }
        } catch ( Exception $e ) {
            $this->log_mensaje("Error actualizando imagenes del producto> " . $e->getMessage());
        }
    }

    public function subirImagen($id_image, $id, $combinacion = false, $type = '') {
        $imageObj = new Image((int)$id_image,(int)Configuration::get('conector_lang'));
        $path = $_SERVER['DOCUMENT_ROOT'] . "/img/p/" . Image::getImgFolderStatic((int)$id_image) . $id_image . $type . '.' . $imageObj->image_format;
        if ( file_exists($path) ) {
            $cimage = new CURLFile($path);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, (string)Configuration::get('external_prestashop_url').'/api/images/products/'.$this->getIdExterno($id, $combinacion));
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_USERPWD, (string)Configuration::get('api_key').':');
            curl_setopt($ch, CURLOPT_POSTFIELDS, array('image' => $cimage));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLINFO_HEADER_OUT, true);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:' ));
            $response = curl_exec($ch);
            $result = $this->parseResponse($response);
            $resources = $result->children()->children();
            $id_externo = $resources->id;
            curl_close($ch);
            return $id_externo;
        }
        return '';
    }

    public function parseResponse($response) {
        log_response($response, "res");
        $index = strpos($response, "\r\n\r\n");
        if ($index === false && $curl_params[CURLOPT_CUSTOMREQUEST] != 'HEAD')
            throw new PrestaShopWebserviceException('Bad HTTP response'.(string)$response.$curl_params[CURLOPT_CUSTOMREQUEST]);
        log_response($response, "respuestasImg");
        $body = substr($response, $index + 4);

        return parseXML($body);
    }

    protected function parseXML($response)
    {
        if ($response != '')
        {
            libxml_clear_errors();
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($response,'SimpleXMLElement', LIBXML_NOCDATA);
            if (libxml_get_errors())
            {
                $msg = var_export(libxml_get_errors(), true);
                libxml_clear_errors();
                throw new PrestaShopWebserviceException('HTTP XML response is not parsable: '.$msg);
            }
            return $xml;
        }
        else
            throw new PrestaShopWebserviceException('HTTP response is empty');
    }

    public function actualizarAtributo($attribute) {
        $ws = new PrestaShopWebservice( (string)Configuration::get('external_prestashop_url'), (string)Configuration::get('api_key'), false);
        if (isset($attribute['id_attribute_group'])) {
            $id_externo = $this->getIdExternoAtributo($attribute['id_attribute_group']);
        }
        if ( $this->exists($attribute['id_attribute_group'], 'id_attribute', 'attributes') ) {
            $nuevo = false;
        } else {
            $nuevo = true;
        }
        if ($nuevo) {
            try {
                $xml = $ws->get(array('url' => (string) Configuration::get('external_prestashop_url').'/api/product_options?schema=blank'));
                $opt = array('resource' => 'product_options');
                $resources = $xml->children()->children();
                $resources->is_color_group = $attribute['is_color_group'];
                $resources->group_type = $attribute['group_type'];
                $resources->name->language[0] = $attribute['name'];
                $resources->public_name->language[0] = $attribute['public_name'];

                $opt['postXml'] = $xml->asXML();
                $respuesta = $ws->add($opt);

                $resources = $respuesta->children()->children();
                $id_externa = $resources->id;

                $this->insertarAtributosEnDb($attribute['id_attribute_group'], $attribute['name'], $id_externa);
                $this->log_mensaje("Éxito añadiendo atributo> ".$attribute['id_attribute_group'] . ">".$xml->asXML()."ENDXML\n".$respuesta->asXML(), "attribute_success");
            } catch ( Exception $e ) {
                $this->log_mensaje("Error añadiendo atributo> ".$attribute['id_attribute_group'] . ">".$e->getMessage());
                $this->log_response("START\n".$xml->asXML()."END","attribute_failure");
            }
        } else {
            try {
                $opt = array('resource' => 'product_options');
                $opt['id'] = $id_externo;

                $xml = $ws->get($opt);
                $resources = $xml->children()->children();
                $resources->group_type = $attribute['group_type'];
                $resources->name->language[0] = $attribute['name'];
                $resources->public_name->language[0] = $attribute['public_name'];
                $opt['putXml'] = $xml->asXML();
                $respuesta = $ws->edit($opt);
                $this->log_response("Éxito actualizando atributo> ".$attribute['id_attribute_group'] . ">".$xml->asXML()."ENDXML\n".$respuesta->asXML(),"attribute_success");
            } catch ( Exception $e ) {
                $this->log_mensaje("Error actualizando atributo> ".$attribute['id_attribute_group'] . ">".$e->getMessage());
                $this->log_response("START\n".$xml->asXML()."END","attribute_failure");
            }
        }
        
    }

    public function actualizarValorAtributo($attribute_value) {
        $ws = new PrestaShopWebservice( (string)Configuration::get('external_prestashop_url'), (string)Configuration::get('api_key'), false);
        if ( isset($attribute_value['id_attribute'])) {
            $id_externo = $this->getIdExternoAtributo($attribute_value['id_attribute'], true);
        }
        if ( $this->exists($attribute_value['id_attribute'], 'id_attribute_value', 'attribute_values')) {
            $nuevo = false;
        } else {
            $nuevo = true;
        }
        if ($nuevo) {
            try {
                $xml = $ws->get(array('url' => (string) Configuration::get('external_prestashop_url').'/api/product_option_values?schema=blank'));
                $opt = array('resource' => 'product_option_values');
                $resources = $xml->children()->children();
                $resources->id_attribute_group = $this->getIdExternoAtributo($attribute_value['id_attribute_group']);
                $resources->name->language[0] = $attribute_value['name'];
                $sql = 'SELECT `color` FROM `'._DB_PREFIX_.'attribute` WHERE `id_attribute`='.$attribute_value['id_attribute'].';'; 
                $color = Db::getInstance()->getValue($sql);
                if ( $color != false && $color != null ) {
                    $resources->color = $color;
                }
                $opt['postXml'] = $xml->asXML();

                $xml = $ws->add($opt);

                $resources = $xml->children()->children();
                $id_externa = $resources->id;

                $this->insertarAtributosEnDb($attribute_value['id_attribute'], $attribute_value['name'], $id_externa, true);
            } catch ( Exception $e ) {
                $this->log_mensaje("Error añadiendo valor de atributo> ".$attribute_value['id_attribute'].">".$e->getMessage());
                $this->log_response($xml->asXML(),"responses");
            }
        } else {
            try {
                $opt = array('resource' => 'product_option_values');
                $opt['id'] = $id_externo;
                $xml = $ws->get($opt);
                $resources = $xml->children()->children();
                $resources->id_attribute_group = $this->getIdExternoAtributo($attribute_value['id_attribute_group']);
                $resources->name->language[0] = $attribute_value['name'];
                $sql = 'SELECT `color` FROM `'._DB_PREFIX_.'attribute` WHERE `id_attribute`='.$attribute_value['id_attribute'].';'; 
                $color = Db::getInstance()->getValue($sql);
                if ( $color != false && $color != null ) {
                    $resources->color = $color;
                }
                $opt['putXml'] = $xml->asXML();
                $xml = $ws->edit($opt);
            } catch ( Exception $e ) {
                $this->log_mensaje("Error actualizando valor de atributo> ".$attribute_value['id_attribute'].">".$e->getMessage());
                $this->log_response($xml->asXML(),"responses");
            }
        }
    }

    public function actualizarCaracteristica($caracteristica) {
        $ws = new PrestaShopWebservice( (string)Configuration::get('external_prestashop_url'), (string)Configuration::get('api_key'), false);
        if (isset($caracteristica['id_feature'])) {
            $id_externo = $this->getIdExternoCaracteristica($caracteristica['id_feature']);
        }
        if ( $this->exists($caracteristica['id_feature'],'id_feature','features')) {
            $nuevo = false;
        } else {
            $nuevo = true;
        }
        if ($nuevo) {
            try {
                $xml = $ws->get(array('url' => (string) Configuration::get('external_prestashop_url').'/api/product_features?schema=blank'));
                $opt = array('resource' => 'product_features');
                $resources = $xml->children()->children();
                $resources->name->language[0] = $caracteristica['name'];

                $opt['postXml'] = $xml->asXML();

                $xml = $ws->add($opt);

                $resources = $xml->children()->children();
                $id_externa = $resources->id;

                $this->insertarCaracteristicasEnDb($caracteristica['id_feature'], $caracteristica['name'], $id_externa);
            } catch ( Exception $e ) {
                $this->log_mensaje("Error añadiendo caracteristica> ".$caracteristica['id_feature'].">".$e->getMessage());
                $this->log_response($xml->asXML(),"responses");
            }
        } else {
            try {
                $opt = array('resource' => 'product_features');
                $opt['id'] = $id_externo;
                $xml = $ws->get($opt);
                $resources = $xml->children()->children();
                $resources->name->language[0] = $caracteristica['name'];
                $opt['putXml'] = $xml->asXML();
                $xml = $ws->edit($opt);
            } catch ( Exception $e ) {
                $this->log_mensaje("Error actualizando caracteristica> ".$caracteristica['id_feature'].">".$e->getMessage());
                $this->log_response($xml->asXML(),"responses");
            }
        }
    }

    public function actualizarValorCaracteristica($valor_caracteristica) {
        $ws = new PrestaShopWebservice( (string)Configuration::get('external_prestashop_url'), (string)Configuration::get('api_key'), false);
        if (isset($valor_caracteristica['id_feature_value'])) {
            $id_externo = $this->getIdExternoCaracteristica($valor_caracteristica['id_feature_value'], true);
        }
        if ( $this->exists($valor_caracteristica['id_feature_value'],'id_feature_value','feature_values')) {
            $nuevo = false;
        } else {
            $nuevo = true;
        }
        if ($nuevo) {
            try {
                $xml = $ws->get(array('url' => (string) Configuration::get('external_prestashop_url').'/api/product_feature_values?schema=blank'));
                $opt = array('resource' => 'product_feature_values');
                $resources = $xml->children()->children();
                $resources->id_feature = $this->getIdExternoCaracteristica($valor_caracteristica['id_feature']);
                $resources->value->language[0] = $valor_caracteristica['value'];
                $resources->custom = $valor_caracteristica['custom'];

                $opt['postXml'] = $xml->asXML();

                $xml = $ws->add($opt);
                $resources = $xml->children()->children();
                $id_externa = $resources->id;

                $this->insertarCaracteristicasEnDb($valor_caracteristica['id_feature_value'], $valor_caracteristica['value'], $id_externa, true);
            } catch ( Exception $e ) {
                $this->log_mensaje("Error añadiendo valor de caracteristica> ".$valor_caracteristica['id_feature_value'].">".$e->getMessage());
                $this->log_response("START\n".$xml->asXML()."END","feature_value_failure");
            }
        } else {
            try {
                $opt = array('resource' => 'product_feature_values');
                $opt['id'] = $id_externo;
                $xml = $ws->get($opt);
                $resources = $xml->children()->children();
                $resources->value->language[0] = $valor_caracteristica['value'];
                $resources->custom = $valor_caracteristica['custom'];
                $opt['putXml'] = $xml->asXML();
                $xml = $ws->edit($opt);
            } catch ( Exception $e ) {
                $this->log_mensaje("Error actualizando valor de caracteristica> ".$valor_caracteristica['id_feature_value'].">".$e->getMessage());
                $this->log_response("START\n".$xml->asXML()."END","feature_value_failure");
            }
        }
    }

    public function muestraRecursiva($array, $respuesta) {
        foreach ( $array as $key => $value ) {
            if (is_array($value)) {
                $respuesta .= $this->muestraRecursiva($value, $respuesta);
            } else {
                $respuesta .= (string) $key . "\n";
            }
        }
        return $respuesta;
    }

    public function installDB(){
        return (Db::getInstance()->execute('
        CREATE TABLE IF NOT EXISTS`'._DB_PREFIX_.'conector_bled_products`( 
            `id_product` INT(10) UNSIGNED NOT NULL PRIMARY KEY,
            `reference` VARCHAR(128) NOT NULL,
            `id_product_ext` INT(10) UNSIGNED NOT NULL,
            `quantity` INT(10) UNSIGNED DEFAULT 0)
            DEFAULT CHARSET=utf8;')
        && Db::getInstance()->execute('
        CREATE TABLE IF NOT EXISTS`'._DB_PREFIX_.'conector_bled_product_attributes`(
            `id_product_attribute` INT(10) UNSIGNED NOT NULL PRIMARY KEY,
            `reference` VARCHAR(128) NOT NULL,
            `id_product_attribute_ext` INT(10) UNSIGNED NOT NULL,
            `quantity` INT(10) UNSIGNED DEFAULT 0 )
            DEFAULT CHARSET=utf8;')
        && Db::getInstance()->execute('
        CREATE TABLE IF NOT EXISTS`'._DB_PREFIX_.'conector_bled_attributes`(
            `id_attribute` INT(10) UNSIGNED NOT NULL PRIMARY KEY,
            `name` VARCHAR(128) NOT NULL,
            `id_attribute_ext` INT(10) UNSIGNED NOT NULL)
            DEFAULT CHARSET=utf8;')
        && Db::getInstance()->execute('
        CREATE TABLE IF NOT EXISTS`'._DB_PREFIX_.'conector_bled_attribute_values`(
            `id_attribute_value` INT(10) UNSIGNED NOT NULL PRIMARY KEY,
            `value` VARCHAR(128) NOT NULL,
            `id_attribute_value_ext` INT(10) UNSIGNED NOT NULL)
            DEFAULT CHARSET=utf8;')
        && Db::getInstance()->execute('
        CREATE TABLE IF NOT EXISTS`'._DB_PREFIX_.'conector_bled_features`(
            `id_feature` INT(10) UNSIGNED NOT NULL PRIMARY KEY,
            `name` VARCHAR(128) NOT NULL,
            `id_feature_ext` INT(10) UNSIGNED NOT NULL)
            DEFAULT CHARSET=utf8;')
        && Db::getInstance()->execute('
        CREATE TABLE IF NOT EXISTS`'._DB_PREFIX_.'conector_bled_feature_values`(
            `id_feature_value` INT(10) UNSIGNED NOT NULL PRIMARY KEY,
            `value` VARCHAR(128) NOT NULL,
            `id_feature_value_ext` INT(10) UNSIGNED NOT NULL)
            DEFAULT CHARSET=utf8;')
        && Db::getInstance()->execute('
        CREATE TABLE IF NOT EXISTS`'._DB_PREFIX_.'conector_bled_images`(
            `id_image` INT(10) UNSIGNED NOT NULL PRIMARY KEY,
            `id_image_ext` INT(10) UNSIGNED)
            DEFAULT CHARSET=utf8;'));
    }

    public function uninstallDB(){
        return (Db::getInstance()->execute('
                DROP TABLE IF EXISTS `'._DB_PREFIX_.'conector_bled_products`;')
            && Db::getInstance()->execute('
                DROP TABLE IF EXISTS `'._DB_PREFIX_.'conector_bled_product_attributes`;')
            && Db::getInstance()->execute('
                DROP TABLE IF EXISTS `'._DB_PREFIX_.'conector_bled_attributes`;')
            && Db::getInstance()->execute('
                DROP TABLE IF EXISTS `'._DB_PREFIX_.'conector_bled_attribute_values`;')
            && Db::getInstance()->execute('
                DROP TABLE IF EXISTS `'._DB_PREFIX_.'conector_bled_features`;')
            && Db::getInstance()->execute('
                DROP TABLE IF EXISTS `'._DB_PREFIX_.'conector_bled_feature_values`;')
            && Db::getInstance()->execute('
                DROP TABLE IF EXISTS `'._DB_PREFIX_.'conector_bled_images`;')
        );
    }

    public function getValues() {
        $query = 'SELECT * FROM `'._DB_PREFIX_.'conector_bled_products`;';
        $result = Db::getInstance()->query($query);
        return $result->fetchAll();
    }

    public function getValuesCombination() {
        $query = 'SELECT * FROM `'._DB_PREFIX_.'conector_bled_product_attributes`;';
        $result = Db::getInstance()->query($query);
        return $result->fetchAll();
    }

    public function getValuesAttribute() {
        $query = 'SELECT * FROM `'._DB_PREFIX_.'conector_bled_attributes`;';
        $result = Db::getInstance()->query($query);
        return $result->fetchAll();
    }

    public function getValuesAttributeValues() {
        $query = 'SELECT * FROM `'._DB_PREFIX_.'conector_bled_attribute_values`;';
        $result = Db::getInstance()->query($query);
        return $result->fetchAll();
    }

    public function getValuesFeatures() {
        $query = 'SELECT * FROM `'._DB_PREFIX_.'conector_bled_features`;';
        $result = Db::getInstance()->query($query);
        return $result->fetchAll();
    }

    public function getValuesFeatureValues() {
        $query = 'SELECT * FROM `'._DB_PREFIX_.'conector_bled_feature_values`;';
        $result = Db::getInstance()->query($query);
        return $result->fetchAll();
    }

    public function getList() {
        return array(
            'id_product' => array('title' => $this->l('ID del producto local'), 'type' => 'text', 'orderby' => true),
            'reference' => array('title' => $this->l('Referencia del producto'), 'type' => 'text', 'orderby' => false),
            'id_product_ext' => array('title' => $this->l('ID del producto externo'), 'type' => 'text', 'orderby' => false),
            'quantity' => array('title' => $this->l('Cantidad en stock'), 'type' => 'int', 'orderby' => false)
        );
    }

    public function getListCombination() {
        return array(
            'id_product_attribute' => array('title' => $this->l('ID de la combinación local'), 'type' => 'text', 'orderby' => true),
            'reference' => array('title' => $this->l('Referencia del producto'), 'type' => 'text', 'orderby' => false),
            'id_product_attribute_ext' => array('title' => $this->l('ID de la combinación externa'), 'type' => 'text', 'orderby' => false),
            'quantity' => array('title' => $this->l('Cantidad en stock'), 'type' => 'int', 'orderby' => false)
        );
    }

    public function getListAtributos() {
        return array(
            'id_attribute' => array('title' => $this->l('ID del atributo'), 'type' => 'text', 'orderby' => true),
            'name' => array('title' => $this->l('Nombre del atributo'), 'type' => 'text', 'orderby' => false),
            'id_attribute_ext' => array('title' => $this->l('ID del atributo externo'), 'type' => 'text', 'orderby' => false)
            );
    }

    public function getListValorAtributos() {
        return array(
            'id_attribute_value' => array('title' => $this->l('ID del valor de atributo'), 'type' => 'text', 'orderby' => true),
            'value' => array('title' => $this->l('Valor del atributo'), 'type' => 'text', 'orderby' => false),
            'id_attribute_value_ext' => array('title' => $this->l('ID del valor de atributo externo'), 'type' => 'text', 'orderby' => false)
            );
    }

    public function getListValorCaracteristicas() {
        return array(
            'id_feature_value' => array('title' => $this->l('ID del valor de caracteristica'), 'type' => 'text', 'orderby' => true),
            'value' => array('title' => $this->l('Valor de la caracteristica'), 'type' => 'text', 'orderby' => false),
            'id_feature_value_ext' => array('title' => $this->l('ID del valor de caracteristica externa'), 'type' => 'text', 'orderby' => false)
            );
    }

    public function getListCaracteristicas() {
        return array(
            'id_feature' => array('title' => $this->l('ID de la caracteristica'), 'type' => 'text', 'orderby' => true),
            'name' => array('title' => $this->l('Nombre de la caracteristica'), 'type' => 'text', 'orderby' => false),
            'id_feature_ext' => array('title' => $this->l('ID de la caracteristica externa'), 'type' => 'text', 'orderby' => false)
            );
    }

    public function insertarEnDb($id_local, $referencia, $id_externa, $combination = false) {
        if ( $combination ) {
            $table = 'conector_bled_product_attributes';
            $column = 'id_product_attribute';
        } else {
            $table = 'conector_bled_products';
            $column = 'id_product';
        }
        try {
            return Db::getInstance()->execute('
                    INSERT INTO `'._DB_PREFIX_.$table.'` (`'.$column.'`,`reference`,`'.$column.'_ext`)
                    VALUES('.$id_local.',\''.$referencia.'\','.$id_externa.');
                    ');
        } catch ( Exception $e ) {
            $this->log_mensaje("Error> ".$e->getMessage());
            return '';
        }
    }

    public function insertarAtributosEnDb($id_local,$name,$id_externa,$value = false) {
        if ( $value ) {
            $table = 'conector_bled_attribute_values';
            $column = 'id_attribute_value';
            $nombre = 'value';
        } else {
            $table = 'conector_bled_attributes';
            $column = 'id_attribute';
            $nombre = 'name';
        }
        try {
            return Db::getInstance()->execute('
                INSERT INTO `'._DB_PREFIX_.$table.'` (`'.$column.'`,`'.$nombre.'`,`'.$column.'_ext`)
                VALUES('.$id_local.',\''.$name.'\','.$id_externa.');'
            );
        } catch ( Exception $e ) {
            try {
                Db::getInstance()->execute('
                    INSERT INTO `'._DB_PREFIX_.$table.'` (`'.$column.'`,`'.$nombre.'`,`'.$column.'_ext`)
                    VALUES('.(990000+$id_local).',\''.$name.'\','.$id_externa.');'
                );
                return true;
            } catch ( Exception $e ) {
                $this->log_mensaje("Error> ".$e->getMessage());
               return '';
            }
        }
    }

    public function insertarCaracteristicasEnDb($id_local, $name, $id_externa, $value = false) {
        if ( $value ) {
            $table = 'conector_bled_feature_values';
            $column = 'id_feature_value';
            $nombre = 'value';
        } else {
            $table = 'conector_bled_features';
            $column = 'id_feature';
            $nombre = 'name';
        }
        try {
            return Db::getInstance()->execute('
                INSERT INTO `'._DB_PREFIX_.$table.'` (`'.$column.'`,`'.$nombre.'`,`'.$column.'_ext`)
                VALUES('.$id_local.',\''.$name.'\','.$id_externa.');'
            );
        } catch ( Exception $e ) {
             try {
                Db::getInstance()->execute('
                    INSERT INTO `'._DB_PREFIX_.$table.'` (`'.$column.'`,`'.$nombre.'`,`'.$column.'_ext`)
                    VALUES('.(990000+$id_local).',\''.$name.'\','.$id_externa.');'
                );
                return true;
            } catch ( Exception $e ) {
                $this->log_mensaje("Error> ".$e->getMessage());
               return '';
            }
        }
    }

    public function insertarImagenesEnDb($id_imagen,$id_imagen_ext) {
        try {
            Db::getInstance()->execute('
                INSERT INTO `'._DB_PREFIX_.'conector_bled_images` (`id_image`)
                VALUES('.$id_imagen.','.$id_imagen_ext.');'
            );
            return true;
        } catch ( Exception $e ) {
            $this->log_mensaje("Error> ".$e->getMessage());
            return '';
        }
    }

    public function imageExists($id_imagen) {
        try {
            $sql = 'SELECT `id_image` FROM `'._DB_PREFIX_.'conector_bled_images`
                    WHERE `id_image`='.$id_imagen.';';
            $result = Db::getInstance()->getValue($sql);
            if ( $result == false || $result == null || $result == '' ) {
                return false;
            } else {
                return true;
            }
        } catch ( Exception $e ) {
            $this->log_mensaje("Error> ".$e->getMessage());
            return false;
        }
    }

    public function getIdExterno($id_local, $combination = false) {
        if ( $combination ) {
            $table = 'conector_bled_attributes';
            $column = 'id_product_attribute';
        } else {
            $table = 'conector_bled_products';
            $column = 'id_product';
        }
        $sql = 'SELECT `'.$column.'_ext` FROM `'._DB_PREFIX_.$table.'` 
               WHERE '.$column.'='.$id_local.';';
        try {
            return Db::getInstance()->getValue($sql);
        } catch ( Exception $e ) {
            $this->log_mensaje("Error> ".$e->getMessage());
            return '';
        }
    }

    public function getIdExternoAtributo($id_local, $value = false) {
        if ( !$value ) {
            $table = 'conector_bled_attributes';
            $column = 'id_attribute';
        } else {
            $table = 'conector_bled_attribute_values';
            $column = 'id_attribute_value';
        }
        $sql = 'SELECT `'.$column.'_ext` FROM `'._DB_PREFIX_.$table.'` 
               WHERE '.$column.'='.$id_local.';';
        try {
            return Db::getInstance()->getValue($sql);
        } catch ( Exception $e ) {
            $this->log_mensaje("Error> ".$e->getMessage());
            return '';
        }
    }

    public function getIdExternoCaracteristica($id_local, $value = false) {
        if ( !$value ) {
            $table = 'conector_bled_features';
            $column = 'id_feature';
        } else {
            $table = 'conector_bled_feature_values';
            $column = 'id_feature_value';
        }
        $sql = 'SELECT `'.$column.'_ext` FROM `'. _DB_PREFIX_.$table.'` 
               WHERE '.$column.'='.$id_local.';';
        try {
            return Db::getInstance()->getValue($sql);
        } catch ( Exception $e ) {
            $this->log_mensaje("Error> ".$e->getMessage());
            return '';
        }
    }

    public function exists($id, $column, $tabla) {
        try {
            $sql = 'SELECT `'.$column.'` FROM '._DB_PREFIX_.'conector_bled_'.$tabla.' WHERE `'.$column.'`='.$id.';';
            $result = Db::getInstance()->getValue($sql);
            if ( $result != '' && $result != null && $result != false ) {
                return true;
            }
            return false;
        } catch ( Exception $e ) {
            $this->log_mensaje($e-getMessage());
            return true;
        }
    }

    public function log_mensaje($mensaje) {
        $log = $_SERVER['DOCUMENT_ROOT'] . '/modules/conector_bled/logs/conector_bled.log';
        $log_file = fopen($log, 'a');
        fwrite($log_file, $mensaje . "\n");
        fclose($log_file);
    }

    public function log_response($response, $name) {
        $log = $_SERVER['DOCUMENT_ROOT'] . '/modules/conector_bled/logs/'.$name.'.log';
        $log_file = fopen($log, 'a');
        fwrite($log_file, $response . "\n");
        fclose($log_file);
    }

    public function reiniciarLogs() {
        $log_folder = $_SERVER['DOCUMENT_ROOT'] . '/modules/conector_bled/logs/';
        $logs = array(
            'caracteristicas',
            'combination',
            'curl',
            'imagenes',
            'responses',
            'conector_bled',
            'product_succes',
            'product_failure',
            'xmls');
        foreach ($logs as $log) {
            $log_url = $log_folder . $log . '.log';
            $log_file = fopen($log_url, 'w');
            fwrite($log_file, "START \n");
            fclose($log_file);
        }
    }

    public function getIdExternos($table, $column) {
        $sql = 'SELECT `'.$column.'_ext` FROM `'._DB_PREFIX_.'conector_bled_'.$table.'`;';
        $result = Db::getInstance()->query($sql);
        return $result->fetchAll();
    }

    public function getProducts($id_lang) {
        $sql = 'SELECT a.`id_product`, 
                       b.`name` AS `name`
                FROM `'._DB_PREFIX_ .'product` a 
                LEFT JOIN `'._DB_PREFIX_.'product_lang` b ON (b.`id_product` = a.`id_product` 
                                                  AND b.`id_lang` =' . $id_lang . '
                                                  AND b.`id_shop` = a.id_shop_default)
                LEFT JOIN `'._DB_PREFIX_ .'stock_available` sav ON (sav.`id_product` = a.`id_product` 
                                                       AND sav.`id_product_attribute` = 0
                                                       AND sav.id_shop = 1  
                                                       AND sav.id_shop_group = 0 )  
                JOIN `'._DB_PREFIX_ .'product_shop` sa ON (a.`id_product` = sa.`id_product` AND sa.id_shop = a.id_shop_default)
                LEFT JOIN `'._DB_PREFIX_ .'category_lang` cl ON (sa.`id_category_default` = cl.`id_category` 
                                                    AND b.`id_lang` = cl.`id_lang` 
                                                    AND cl.id_shop = a.id_shop_default)
                LEFT JOIN `'._DB_PREFIX_ .'shop` shop ON (shop.id_shop = a.id_shop_default)
                LEFT JOIN `'._DB_PREFIX_ .'image_shop` image_shop ON (image_shop.`id_product` = a.`id_product` 
                                                         AND image_shop.`cover` = 1 
                                                         AND image_shop.id_shop = a.id_shop_default)
                LEFT JOIN `'._DB_PREFIX_ .'image` i ON (i.`id_image` = image_shop.`id_image`)
                LEFT JOIN `'._DB_PREFIX_ .'product_download` pd ON (pd.`id_product` = a.`id_product`) 
             WHERE 1
             ORDER BY a.`price` ASC;';
        $result = Db::getInstance()->query($sql);
        if (!$result && $result != '' && !empty($result) && $result != null) {
            return $result->fetchAll();
        } else {
            return $result;
        }
    }

    public function borrar_basura() {
        $ws = new PrestaShopWebservice( (string)Configuration::get('external_prestashop_url'), (string)Configuration::get('api_key'), false);
        try {
            $objetos = array(
                            'products' => 'product',
                            'images/products' => 'image',
                            'combinations' => 'product_attribute',
                            'product_options' => 'attribute',
                            'product_option_values' => 'attribute_value',
                            'product_features' => 'feature',
                            'product_feature_values' => 'feature_value'
                        );
            foreach ( $objetos as $objeto => $valor ) {
                $xml = $ws->get(array('url' => (string) Configuration::get('external_prestashop_url').'/api/'.$objeto));
                $resources = $xml->children();
                $objs = xml2array($resources->asXML());
                foreach ( $objs as $obj ) {
                    if ( !$this->exists($obj, 'id_'.$valor.'_ext', $valor.'s') ) {
                        try {
                            $opt = array( 'resources' => $objeto );
                            $opt['id'] = $obj;
                            $respuesta = $ws->delete($opt);
                        } catch (Exception $e) {

                        }
                    }
                }
            }
            
        } catch ( Exception $e ) {
            $log_mensaje("No se pudo borrar la basura");
        }
    }

    public function borrar_producto($id_product) {
        $ws = new PrestaShopWebservice( (string)Configuration::get('external_prestashop_url'), (string)Configuration::get('api_key'), false);
        try {
            $opt = array('resource' => 'products');
            $opt['id'] = $this->getIdExterno($id_product);
            $xml = $ws->delete($opt);
            $where = '`id_product`='.$id_product;
            return Db::getInstance()->delete(_DB_PREFIX_.'conector_bled_products',$where);
        } catch ( Exception $e ) {
            $this->log_mensaje("Error borrando producto> ".$e->getMessage());
            return false;
        }
    }

    public function borrar_atributo($id_atributo) {
        $ws = new PrestaShopWebservice( (string)Configuration::get('external_prestashop_url'), (string)Configuration::get('api_key'), false);
        try {
            $opt = array('resource' => 'product_options');
            $opt['id'] = $this->getIdExternoAtributo($id_atributo);
            $xml = $ws->delete($opt);
            $where = '`id_attribute`='.$id_valor;
            return Db::getInstance()->delete(_DB_PREFIX_.'conector_bled_attributes',$where);
        } catch ( Exception $e ) {
            $this->log_mensaje("Error borrando atributo> ".$e->getMessage());
            return false;
        }
    }

    public function borrar_valor_atributo($id_valor) {
        $ws = new PrestaShopWebservice( (string)Configuration::get('external_prestashop_url'), (string)Configuration::get('api_key'), false);
        try {
            $opt = array('resource' => 'product_option_values');
            $opt['id'] = $this->getIdExternoAtributo($id_valor, true);
            $xml = $ws->delete($opt);
            $where = '`id_attribute_value`='.$id_valor;
            return Db::getInstance()->delete(_DB_PREFIX_.'conector_bled_attribute_values',$where);
        } catch ( Exception $e ) {
            $this->log_mensaje("Error borrando valor de atributo> ".$e->getMessage());
            return false;
        }
    }

    public function borrar_caracteristica($id_caracteristica) {
        $ws = new PrestaShopWebservice( (string)Configuration::get('external_prestashop_url'), (string)Configuration::get('api_key'), false);
        try {
            $opt = array('resource' => 'product_features');
            $opt['id'] = $this->getIdExternoCaracteristica($id_caracteristica);
            $xml = $ws->delete($opt);
            $where = '`id_feature`='.$id_caracteristica;
            return Db::getInstance()->delete(_DB_PREFIX_.'conector_bled_features',$where);
        } catch ( Exception $e ) {
            $this->log_mensaje("Error borrando caracteristica> ".$e->getMessage());
            return false;
        }
    }

    public function borrar_valor_caracteristica($id_valor) {
        $ws = new PrestaShopWebservice( (string)Configuration::get('external_prestashop_url'), (string)Configuration::get('api_key'), false);
        try {
            $opt = array('resource' => 'product_feature_values');
            $opt['id'] = $this->getIdExternoCaracteristica($id_valor);
            $xml = $ws->delete($opt);
            $where = '`id_feature_value`='.$id_valor;
            return Db::getInstance()->delete(_DB_PREFIX_.'conector_bled_feature_values',$where);
        } catch ( Exception $e ) {
            $this->log_mensaje("Error borrando valor de caracteristica> ".$e->getMessage());
            return false;
        }
    }

    public function borrar_productos() {
        $ws = new PrestaShopWebservice( (string)Configuration::get('external_prestashop_url'), (string)Configuration::get('api_key'), false);
        $productos = $this->getIdExternos('products', 'id_product');
        foreach ( $productos as $id_producto_ext ) {
            try {
                $opt = array('resource' => 'products');
                $opt['id'] = $id_producto_ext;
                $xml = $ws->delete($opt);
            } catch ( Exception $e ) {
                $this->log_mensaje("Error> ".$e->getMessage());
                return false;
            }
        }
        return true;
    }

    public function borrar_atributos() {
        $ws = new PrestaShopWebservice( (string)Configuration::get('external_prestashop_url'), (string)Configuration::get('api_key'), false);
        $atributos = $this->getIdExternos('attributes', 'id_attribute');
        foreach ( $atributos as $id_atributo_ext ) {
            try {
                $opt = array('resource' => 'product_options');
                $opt['id'] = $id_atributo_ext;
                $xml = $ws->delete($opt);
            } catch ( Exception $e ) {
                $this->log_mensaje("Error> ".$e->getMessage());
                return false;
            }
        }
        return true;
    }

    public function borrar_caracteristicas() {
        $ws = new PrestaShopWebservice( (string)Configuration::get('external_prestashop_url'), (string)Configuration::get('api_key'), false);
        $caracteristicas = $this->getIdExternos('features', 'id_feature');
        foreach ( $caracteristicas as $id_feature_ext ) {
            try {
                $opt = array('resource' => 'product_features');
                $opt['id'] = $id_feature_ext;
                $xml = $ws->delete($opt);
            } catch ( Exception $e ) {
                $this->log_mensaje("Error> ".$e->getMessage());
                return false;
            }
        }
        return true;
    }

    public function borrar_todo() {
        return ($this->borrar_productos()
        && $this->borrar_atributos()
        && $this->borrar_caracteristicas());
    }

    public function setStatus($active) {
        $path = $_SERVER['DOCUMENT_ROOT'] . '/modules/conector_bled/lib/activo';
        $file = fopen($path, "w");
        if ( $active ) {
            fwrite($file, 'true');
        } else {
            fwrite($file, 'false');
        }
        fclose($file);
    }

    public function getStatus() {
        $path = $_SERVER['DOCUMENT_ROOT'] . '/modules/conector_bled/lib/activo';
        $file = fopen($path, "r");
        $status = fread($file, filesize($path));
        fclose($file);
        if ( $status == 'true' ) {
            return true;
        } else {
            return false;
        }
    }

    function xml2array($fname){
      $sxi = new SimpleXmlIterator($fname);
      return sxiToArray($sxi);
    }

    function sxiToArray($sxi){
      $a = array();
      for ( $sxi->rewind(); $sxi->valid(); $sxi->next() ) {
        array_push($a, (int)($sxi->current()['id']));
      }
      return $a;
    }
}

?>