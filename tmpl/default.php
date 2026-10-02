<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Language\Text;

/**
 * @var \Joomla\Registry\Registry $params
 * @var \stdClass                 $module
 * @var array                     $fields
 * @var string                    $mode
 * @var string                    $formId
 * @var string                    $triggerSelector
 * @var array                     $after
 * @var array                     $goal
 * @var string                    $leadHtml
 * @var bool                      $rfMissing
 * @var bool                      $uploadOff
 */

$e       = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$sfx     = trim((string) $params->get('moduleclass_sfx', ''));
$title   = trim((string) $params->get('title', ''));
$lead    = (string) $leadHtml;
$formTpl = ModuleHelper::getLayoutPath('mod_dharma_form', '_form');

if ($uploadOff) : ?>
	<div class="alert alert-warning"><?php echo Text::_('MOD_DHARMA_FORM_UPLOAD_OFF'); ?></div>
<?php endif;

if ($rfMissing) : ?>
	<div class="alert alert-warning"><?php echo Text::_('MOD_DHARMA_FORM_RF_MISSING'); ?></div>
<?php endif;

if (!$fields) {
	return;
}

if ($mode === 'modal') :
	$triggerText = trim((string) $params->get('trigger_text', ''));
	?>
	<?php if ($triggerText !== '') : ?>
		<button type="button" class="<?php echo $e($params->get('button_class', 'knopka')); ?> dharma-form__trigger" data-dharma-form-open="#<?php echo $e($formId); ?>_modal"><?php echo $e($triggerText); ?></button>
	<?php endif; ?>

	<div class="modal fade dharma-form-modal" id="<?php echo $e($formId); ?>_modal" tabindex="-1" aria-hidden="true"
		<?php if ($title !== '') : ?>aria-labelledby="<?php echo $e($formId); ?>_title"<?php endif; ?>
		data-dharma-form-trigger="<?php echo $e($triggerSelector); ?>">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<button type="button" class="btn-close dharma-form-modal__close" data-bs-dismiss="modal" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
				<?php if ($title !== '') : ?>
					<div class="modal-header">
						<h2 class="modal-title" id="<?php echo $e($formId); ?>_title"><?php echo $e($title); ?></h2>
					</div>
				<?php endif; ?>
				<div class="modal-body">
					<?php if ($lead !== '') : ?><div class="dharma-form__lead"><?php echo $lead; ?></div><?php endif; ?>
					<?php include $formTpl; ?>
				</div>
			</div>
		</div>
	</div>
<?php else : ?>
	<div class="mod-dharma-form<?php echo $sfx !== '' ? ' ' . $e($sfx) : ''; ?>">
		<?php if ($title !== '') : ?><h3 class="dharma-form__title"><?php echo $e($title); ?></h3><?php endif; ?>
		<?php if ($lead !== '') : ?><div class="dharma-form__lead"><?php echo $lead; ?></div><?php endif; ?>
		<?php include $formTpl; ?>
	</div>
<?php endif; ?>
