<?php

namespace Joomla\Module\DharmaForm\Site\Dispatcher;

\defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;

/**
 * Диспетчер модуля "Конструктор форм".
 */
class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
	use HelperFactoryAwareTrait;

	/**
	 * Данные для шаблона.
	 *
	 * @return  array
	 */
	protected function getLayoutData(): array
	{
		$data   = parent::getLayoutData();
		$params = $data['params'];
		$helper = $this->getHelperFactory()->getHelper('FormHelper');

		$data['fields']      = $helper->getFields($params);
		$data['mode']        = $params->get('mode', 'simple') === 'modal' ? 'modal' : 'simple';
		$data['leadHtml']    = $helper->cleanHtml((string) $params->get('lead', ''));
		$data['goal']        = $helper->getGoal($params);
		$data['after']       = $helper->getAfterSettings($params, $data['mode']);
		$data['triggerSelector'] = $helper->getTriggerSelector($params);
		$data['formId']      = 'dharma_form_' . (int) $data['module']->id;

		// Предупреждение о выключенном RadicalForm видят только те, кто управляет модулями.
		$data['rfMissing'] = !PluginHelper::isEnabled('system', 'radicalform')
			&& $this->getApplication()->getIdentity()->authorise('core.manage', 'com_modules');

		// Загрузка файлов должна быть включена в самом плагине (вкладка «Файлы»).
		$data['uploadOff'] = false;

		if ($data['rfMissing'] === false && PluginHelper::isEnabled('system', 'radicalform')
			&& array_filter($data['fields'], static fn (array $field): bool => $field['type'] === 'file')
			&& $this->getApplication()->getIdentity()->authorise('core.manage', 'com_modules')) {
			$plugin = PluginHelper::getPlugin('system', 'radicalform');

			$data['uploadOff'] = (int) (new Registry($plugin->params ?? ''))->get('uploadenabled', 0) !== 1;
		}

		$wa = $this->getApplication()->getDocument()->getWebAssetManager();
		$wa->getRegistry()->addExtensionRegistryFile('mod_dharma_form');
		$wa->useStyle('mod_dharma_form.form');
		$wa->useScript('mod_dharma_form.form');

		if ($data['mode'] === 'modal') {
			$wa->useScript('bootstrap.modal');
		}

		return $data;
	}
}
