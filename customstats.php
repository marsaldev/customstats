<?php
/**
 * 2007-2020 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0).
 * It is also available through the world-wide-web at this URL: https://opensource.org/licenses/AFL-3.0
 */

declare(strict_types=1);

class CustomStats extends Module
{
    public function __construct()
    {
        $this->name = 'customstats';
        $this->tab = 'dashboard';
        $this->version = '1.0';
        $this->author = 'PrestaShop';
        $this->need_instance = 0;
        $this->bootstrap = 1;

        parent::__construct();

        $this->displayName = $this->trans('Custom stats on Dashboard', [], 'Modules.Customstats.Admin');
        $this->description = $this->trans('This module shows custom stats on dashboard', [], 'Modules.Customstats.Admin');
        $this->ps_versions_compliancy = array('min' => '1.7.6.0', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        return parent::install() &&
            $this->registerHook('dashboardZoneOne') &&
            $this->registerHook('dashboardZoneTwo');
    }

    public function hookDashboardZoneOne($params)
    {
        $best_customer = $this->getBestCustomer($params['date_from'], $params['date_to']);

        $this->context->smarty->assign([
            'firstname' => $best_customer['firstname'],
            'lastname' => $best_customer['lastname'],
            'totalMoneySpent' => $best_customer['totalMoneySpent']
        ]);

        return $this->display(__FILE__, '/views/templates/admin/bestcustomer.tpl');
    }

    public function hookDashboardZoneTwo($params)
    {
        return 'DashboardZoneTwo';
    }

    private function getBestCustomer($date_from, $date_to)
    {
        $this->query = '
		SELECT SQL_CALC_FOUND_ROWS c.`id_customer`, c.`lastname`, c.`firstname`, c.`email`,
			COUNT(co.`id_connections`) as totalVisits,
			IFNULL((
				SELECT ROUND(SUM(IFNULL(op.`amount`, 0) / cu.conversion_rate), 2)
				FROM `'._DB_PREFIX_.'orders` o
				LEFT JOIN `'._DB_PREFIX_.'order_payment` op ON o.reference = op.order_reference
				LEFT JOIN `'._DB_PREFIX_.'currency` cu ON o.id_currency = cu.id_currency
				WHERE o.id_customer = c.id_customer
				AND o.invoice_date BETWEEN "'.$date_from.' 00:00:00" AND "'.$date_to.' 23:59:59"
				AND o.valid
			), 0) as totalMoneySpent,
			IFNULL((
				SELECT COUNT(*)
				FROM `'._DB_PREFIX_.'orders` o
				WHERE o.id_customer = c.id_customer
				AND o.invoice_date BETWEEN "'.$date_from.' 00:00:00" AND "'.$date_to.' 23:59:59"
				AND o.valid
			), 0) as totalValidOrders
		FROM `'._DB_PREFIX_.'customer` c
		LEFT JOIN `'._DB_PREFIX_.'guest` g ON c.`id_customer` = g.`id_customer`
		LEFT JOIN `'._DB_PREFIX_.'connections` co ON g.`id_guest` = co.`id_guest`
		WHERE co.date_add BETWEEN "'.$date_from.' 00:00:00" AND "'.$date_to.' 23:59:59"'
            .Shop::addSqlRestriction(Shop::SHARE_CUSTOMER, 'c').
            ' GROUP BY c.`id_customer`, c.`lastname`, c.`firstname`, c.`email`'.
        ' ORDER BY totalMoneySpent DESC';

        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($this->query);

        return $result;
    }

    private function getBestCustomerNoParams()
    {
        $this->query = '
		SELECT SQL_CALC_FOUND_ROWS c.`id_customer`, c.`lastname`, c.`firstname`, c.`email`,
			COUNT(co.`id_connections`) as totalVisits,
			IFNULL((
				SELECT ROUND(SUM(IFNULL(op.`amount`, 0) / cu.conversion_rate), 2)
				FROM `'._DB_PREFIX_.'orders` o
				LEFT JOIN `'._DB_PREFIX_.'order_payment` op ON o.reference = op.order_reference
				LEFT JOIN `'._DB_PREFIX_.'currency` cu ON o.id_currency = cu.id_currency
				WHERE o.id_customer = c.id_customer
				AND o.invoice_date BETWEEN '.$this->getDate().'
				AND o.valid
			), 0) as totalMoneySpent,
			IFNULL((
				SELECT COUNT(*)
				FROM `'._DB_PREFIX_.'orders` o
				WHERE o.id_customer = c.id_customer
				AND o.invoice_date BETWEEN '.$this->getDate().'
				AND o.valid
			), 0) as totalValidOrders
		FROM `'._DB_PREFIX_.'customer` c
		LEFT JOIN `'._DB_PREFIX_.'guest` g ON c.`id_customer` = g.`id_customer`
		LEFT JOIN `'._DB_PREFIX_.'connections` co ON g.`id_guest` = co.`id_guest`
		WHERE co.date_add BETWEEN '.$this->getDate()
            .Shop::addSqlRestriction(Shop::SHARE_CUSTOMER, 'c').
            ' GROUP BY c.`id_customer`, c.`lastname`, c.`firstname`, c.`email`'.
            ' ORDER BY totalMoneySpent DESC';

        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($this->query);

        return $result;
    }

    public function getDate()
    {
        return ModuleGraph::getDateBetween($this->context->employee->id);
    }
}
