<?php
/**
 * 2006-2020 THECON SRL
 *
 * NOTICE OF LICENSE
 *
 * DISCLAIMER
 *
 * YOU ARE NOT ALLOWED TO REDISTRIBUTE OR RESELL THIS FILE OR ANY OTHER FILE
 * USED BY THIS MODULE.
 *
 * @author    THECON SRL <contact@thecon.ro>
 * @copyright 2006-2020 THECON SRL
 * @license   Commercial
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once(dirname(__FILE__) . '/classes/WoopraTracker.php');
class Thwoopra extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'thwoopra';
        $this->tab = 'analytics_stats';
        $this->version = '1.0.0';
        $this->author = 'Thecon';
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;
        $this->module_key = 'be1785ed5f7d53528e86b7cb9f999a6b';

        parent::__construct();

        $this->displayName = $this->l('Woopra Integration');
        $this->description = $this->l('Woopra eCommerce Tracking');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        Configuration::updateValue('THWOOPRA_LIVE_MODE', false);
        $domain = Tools::getShopDomain(false).__PS_BASE_URI__;
        Configuration::updateValue('THWOOPRA_DOMAIN', Tools::substr($domain, 0, -1));

        return parent::install() &&
            $this->registerWoopraHooks();
    }

    public function uninstall()
    {
        $form_values = $this->getConfigFormValues();
        foreach (array_keys($form_values) as $key) {
            Configuration::deleteByName($key);
        }

        return parent::uninstall();
    }

    public function registerWoopraHooks()
    {
        if ($this->getPsVersion() == '7') {
            $this->registerHook('displayBeforeBodyClosingTag');
        } else {
            $this->registerHook('displayFooter');
        }
        $this->registerHook('actionAuthentication');
        $this->registerHook('actionCustomerAccountAdd');
        $this->registerHook('header');
        $this->registerHook('displayOrderConfirmation');
        $this->registerHook('actionOrderStatusPostUpdate');
        return true;
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        $confirm = '';
        if (((bool)Tools::isSubmit('submitThwoopraModule')) == true) {
            if ($this->postProcess()) {
                $confirm = $this->displayConfirmation($this->l('Successful update.'));
            } else {
                $confirm = $this->displayError($this->l('Complete Woopra Domain Config field!'));
            }
        }

        $this->context->smarty->assign('module_dir', $this->_path);
        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $output.$confirm.$this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitThwoopraModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        $order_states = OrderState::getOrderStates(Configuration::get('PS_LANG_DEFAULT'));
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Enable Tracking:'),
                        'name' => 'THWOOPRA_LIVE_MODE',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Woopra Domain Config'),
                        'name' => 'THWOOPRA_DOMAIN',
                        'col' => 3,
                        'desc' => $this->l('This value is used to configure Woopra Snippet')
                    ),
                    array(
                        'type' => 'html',
                        'label' => $this->l('Track Settings'),
                        'name' => '<hr>',
                        'col' => 4
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('View Product:'),
                        'name' => 'THWOOPRA_TRACK_VIEW',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'view_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'view_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('New Order:'),
                        'name' => 'THWOOPRA_TRACK_ORDER',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'order_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'order_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Contact Form Message:'),
                        'name' => 'THWOOPRA_TRACK_CF_MESSAGE',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'cf_messages_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'cf_messages_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('User Signup:'),
                        'name' => 'THWOOPRA_TRACK_SIGNUP',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'signup_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'signup_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('User Login:'),
                        'name' => 'THWOOPRA_TRACK_LOGIN',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'login_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'login_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'type' => 'html',
                        'label' => '',
                        'name' => '<hr>',
                        'col' => 4
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Order Shipped:'),
                        'name' => 'THWOOPRA_TRACK_SHIPPED',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'shipped_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'shipped_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'type' => 'select',
                        'name' => 'THWOOPRA_SHIPPED_STATE',
                        'label' => $this->l('Order Shipped State:'),
                        'options' => array(
                            'query' => $order_states,
                            'id' => 'id_order_state',
                            'name' => 'name'
                        ),
                    ),
                    array(
                        'type' => 'html',
                        'label' => '',
                        'name' => '<hr>',
                        'col' => 4
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Order Refunded:'),
                        'name' => 'THWOOPRA_TRACK_REFUNDED',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'refunded_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'refunded_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'type' => 'select',
                        'name' => 'THWOOPRA_REFUNDED_STATE',
                        'label' => $this->l('Order Refunded State:'),
                        'options' => array(
                            'query' => $order_states,
                            'id' => 'id_order_state',
                            'name' => 'name'
                        ),
                    ),
                    array(
                        'type' => 'html',
                        'label' => '',
                        'name' => '<hr>',
                        'col' => 4
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Order Canceled:'),
                        'name' => 'THWOOPRA_TRACK_CANCELED',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'canceled_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'canceled_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'type' => 'select',
                        'name' => 'THWOOPRA_CANCELED_STATE',
                        'label' => $this->l('Order Canceled State:'),
                        'options' => array(
                            'query' => $order_states,
                            'id' => 'id_order_state',
                            'name' => 'name'
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'THWOOPRA_LIVE_MODE' => Configuration::get('THWOOPRA_LIVE_MODE'),
            'THWOOPRA_TRACK_VIEW' => Configuration::get('THWOOPRA_TRACK_VIEW'),
            'THWOOPRA_TRACK_ORDER' => Configuration::get('THWOOPRA_TRACK_ORDER'),
            'THWOOPRA_TRACK_CF_MESSAGE' => Configuration::get('THWOOPRA_TRACK_CF_MESSAGE'),
            'THWOOPRA_TRACK_SIGNUP' => Configuration::get('THWOOPRA_TRACK_SIGNUP'),
            'THWOOPRA_TRACK_LOGIN' => Configuration::get('THWOOPRA_TRACK_LOGIN'),
            'THWOOPRA_TRACK_SHIPPED' => Configuration::get('THWOOPRA_TRACK_SHIPPED'),
            'THWOOPRA_SHIPPED_STATE' => Configuration::get('THWOOPRA_SHIPPED_STATE'),
            'THWOOPRA_TRACK_REFUNDED' => Configuration::get('THWOOPRA_TRACK_REFUNDED'),
            'THWOOPRA_REFUNDED_STATE' => Configuration::get('THWOOPRA_REFUNDED_STATE'),
            'THWOOPRA_TRACK_CANCELED' => Configuration::get('THWOOPRA_TRACK_CANCELED'),
            'THWOOPRA_CANCELED_STATE' => Configuration::get('THWOOPRA_CANCELED_STATE'),
            'THWOOPRA_DOMAIN' => Configuration::get('THWOOPRA_DOMAIN')
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        if (!Tools::getValue('THWOOPRA_DOMAIN')) {
            return false;
        }

        $form_values = $this->getConfigFormValues();
        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
        return true;
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        if (Configuration::get('THWOOPRA_LIVE_MODE')) {
            $this->smarty->assign(
                array(
                    'w_url' => Configuration::get('THWOOPRA_DOMAIN')
                )
            );
            return $this->display(__FILE__, 'header.tpl');
        }
    }

    public function hookActionOrderStatusPostUpdate($params)
    {
        $tracking = Configuration::get('THWOOPRA_LIVE_MODE');
        if ($tracking) {
            $new_state_id = $params['newOrderStatus']->id;
            $order = new Order($params['id_order']);
            $event = '';
            $properties = array();
            $track_shipped = Configuration::get('THWOOPRA_TRACK_SHIPPED');
            if ($track_shipped) {
                $id_fulilled = Configuration::get('THWOOPRA_SHIPPED_STATE');
                if ($new_state_id == $id_fulilled) {
                    $event = 'product shipped';
                    $carrier = new Carrier($order->id_carrier, Configuration::get('PS_LANG_DEFAULT'));
                    $properties = array(
                        'order_id' => $order->reference,
                        'shipping_method' => $carrier->name
                    );
                }
            }

            $track_canceled = Configuration::get('THWOOPRA_TRACK_CANCELED');
            if ($track_canceled) {
                $id_canceled = Configuration::get('THWOOPRA_CANCELED_STATE');
                if ($new_state_id == $id_canceled) {
                    $event = 'product canceled';
                    $properties = array(
                        'order_id' => $order->reference
                    );
                }
            }

            $track_refounded = Configuration::get('THWOOPRA_TRACK_REFUNDED');
            if ($track_refounded) {
                $id_refounded = Configuration::get('THWOOPRA_REFUNDED_STATE');
                if ($new_state_id == $id_refounded) {
                    $event = 'issue refund';
                    $properties = array(
                        'order_id' => $order->reference,
                        'amount' => round($order->total_paid_tax_incl, 2)
                    );
                }
            }

            if ($event) {
                $woopra = new WoopraTracker(array("domain" => Configuration::get('THWOOPRA_DOMAIN')));
                $woopra->setWoopraCookie();

                $customer = new Customer($order->id_customer);
                $woopra->identify(array(
                    "name" => $customer->firstname.' '.$customer->lastname,
                    "email" => $customer->email,
                ));
                $woopra->push();
                $woopra->track($event, $properties);
            }
        }
        return true;
    }

    public function hookActionAuthentication($params)
    {
        $active = Configuration::get('THWOOPRA_LIVE_MODE');
        $track_login = Configuration::get('THWOOPRA_TRACK_LOGIN');
        if ($active && $track_login) {
            $customer = $params['customer'];
            $woopra = new WoopraTracker(array("domain" => Configuration::get('THWOOPRA_DOMAIN')));
            $woopra->setWoopraCookie();
            $woopra->identify(array(
                "name" => $customer->firstname.' '.$customer->lastname,
                "email" => $customer->email,
            ));
            $woopra->push();
            $woopra->track("login");
        }
    }

    public function hookActionCustomerAccountAdd($params)
    {
        $active = Configuration::get('THWOOPRA_LIVE_MODE');
        $track_signup = Configuration::get('THWOOPRA_TRACK_LOGIN');
        if ($active && $track_signup) {
            $customer = $params['newCustomer'];
            $woopra = new WoopraTracker(array("domain" => Configuration::get('THWOOPRA_DOMAIN')));
            $woopra->setWoopraCookie();
            $woopra->identify(array(
                "name" => $customer->firstname.' '.$customer->lastname,
                "email" => $customer->email,
            ));
            $woopra->push();
            $woopra->track("signup");
        }
    }

    public function hookDisplayBeforeBodyClosingTag()
    {
        if (Configuration::get('THWOOPRA_LIVE_MODE')) {
            $controller = Tools::getValue('controller');
            $product_vars = array();
            if ($controller == 'product') {
                $id_product = Tools::getValue('id_product');
                $id_product_attribute = Tools::getValue('id_product_attribute');
                $product_object = new Product($id_product, false, $this->context->language->id);
                $product_vars['name'] = $product_object->name;
                $price = $product_object->getPrice(
                    true,
                    $id_product_attribute,
                    2
                );
                $product_vars['price'] = round($price, 2);
                $product_vars['reference'] = $product_object->reference;
                $category_obj = new Category($product_object->id_category_default);
                $product_vars['category'] = $category_obj->getName($this->context->language->id);
                $product_vars['url'] = $product_object->getLink();
            }

            $track_cf = false;
            if (Tools::isSubmit('submitMessage')) {
                $track_cf = Configuration::get('THWOOPRA_TRACK_CF_MESSAGE');
                $w_message = Tools::getValue('message');
                $contact_id = (int)Tools::getValue('id_contact');
                $contact = new Contact($contact_id, $this->context->language->id);
                $w_subject = $contact->name;
                $w_from = Tools::getValue('from');
            }

            $this->smarty->assign(
                array(
                    'w_logged' => $this->context->customer->isLogged(),
                    'w_name' => $this->context->customer->firstname.' '.$this->context->customer->lastname,
                    'w_email' => $this->context->customer->email,
                    'w_controller' => $controller,
                    'w_prod_vars' => $product_vars,
                    'w_subject' => isset($w_subject) ? $w_subject : '',
                    'w_message' => isset($w_message) ? $w_message : '',
                    'w_from' => isset($w_from) ? $w_from : '',
                    'track_cf' => $track_cf,
                    'track_pv' => Configuration::get('THWOOPRA_TRACK_VIEW')
                )
            );
            return $this->display(__FILE__, 'footer.tpl');
        }
        return false;
    }

    public function hookDisplayOrderConfirmation($params)
    {
        $active = Configuration::get('THWOOPRA_LIVE_MODE');
        $track_order = Configuration::get('THWOOPRA_TRACK_ORDER');
        if ($active && $track_order) {
            if ($this->getPsVersion() == '7') {
                $order = $params['order'];
            } else {
                $order = $params['objOrder'];
            }
            $items = array();
            foreach ($order->getProducts() as $product) {
                $category_obj = new Category($product['id_category_default']);
                $category_name = $category_obj->getName($this->context->language->id);
                $product_obj = new Product($product['id_product'], false, $this->context->language->id);
                $product_url = $product_obj->getLink();
                $item = array(
                    'name' => $product['product_name'],
                    'price' => round($product['product_price_wt'], 2),
                    'sku' => $product['product_reference'],
                    'url' => $product_url,
                    'category' => $category_name,
                    'quantity' => $product['product_quantity'],
                );
                $items[] = $item;
            }
            $this->context->smarty->assign(array(
                'w_ordered' => $items,
                'w_discount_amount' => round($order->total_discounts, 2),
                'w_tax_amount' => round($order->total_paid_tax_incl - $order->total_paid_tax_excl, 2),
                'w_shipping_amount' => round($order->total_shipping, 2),
                'w_total_amount' => round($order->total_paid_tax_incl, 2),
                'w_reference' => $order->reference
            ));
            return $this->display(__FILE__, 'order.tpl');
        }
        return false;
    }

    public function hookDisplayFooter()
    {
        return $this->hookDisplayBeforeBodyClosingTag();
    }

    public function getPsVersion()
    {
        $full_version = _PS_VERSION_;
        return explode(".", $full_version)[1];
    }
}
