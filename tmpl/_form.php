<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/**
 * Разметка самой формы под плагин RadicalForm: .rf-form + кнопка .rf-button-send,
 * у каждого поля обязателен атрибут name.
 *
 * @var \Joomla\Registry\Registry $params
 * @var array                     $fields
 * @var string                    $formId
 * @var array                     $after
 * @var array                     $goal
 * @var string                    $mode
 */

$e          = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$showLabels = (int) $params->get('show_labels', 0) === 1;
$subject    = trim((string) $params->get('rf_subject', ''));
$target     = trim((string) $params->get('rf_target', ''));
$rfText     = trim((string) $params->get('rf_text', ''));
$btnClass   = trim((string) $params->get('button_class', ''));
$star       = ' <span class="dharma-form__star" aria-hidden="true">*</span>';

// Место под текст ошибки: JS показывает его, когда RadicalForm помечает поле невалидным.
$errorEl = static fn (array $field): string => '<div class="dharma-form__error" role="alert" hidden'
	. ' data-msg-empty="' . $e($field['errorEmpty']) . '" data-msg="' . $e($field['errorText']) . '"></div>';

?>
<form id="<?php echo $e($formId); ?>" class="rf-form dharma-form" novalidate
	data-after="<?php echo $e($after['action']); ?>"
	<?php if ($after['action'] === 'redirect') : ?>data-redirect="<?php echo $e($after['redirect']); ?>" data-redirect-delay="<?php echo (int) $after['redirectDelay'] * 1000; ?>"<?php endif; ?>
	<?php if ($after['close']) : ?>data-close="1" data-close-delay="<?php echo (int) $after['closeDelay'] * 1000; ?>"<?php endif; ?>
	<?php if ($goal['name'] !== '') : ?>data-goal="<?php echo $e($goal['name']); ?>"<?php echo $goal['ym'] > 0 ? ' data-goal-ym="' . (int) $goal['ym'] . '"' : ''; ?><?php endif; ?>>
	<?php foreach ($fields as $i => $field) :
		$inputId = $formId . '_' . $field['name'];

		// Скрытое поле: значение подставляет JS (адрес, заголовок) или задано в настройках.
		if ($field['type'] === 'hidden') : ?>
			<input type="hidden" name="<?php echo $e($field['name']); ?>" value="<?php echo $e($field['source'] === 'custom' ? $field['value'] : ''); ?>" data-dharma-hidden="<?php echo $e($field['source']); ?>" data-value="<?php echo $e($field['value']); ?>">
			<?php continue;
		endif;

		if ($field['type'] === 'file') :
			$fileAttr = 'type="file" name="' . $e($field['name']) . '" id="' . $e($inputId) . '" class="rf-upload-button"'
				. ($field['multiple'] ? ' multiple' : '')
				. ($field['accept'] !== '' ? ' accept="' . $e($field['accept']) . '"' : '')
				. ($field['required'] ? ' data-dharma-file-required="' . $e(Text::_('MOD_DHARMA_FORM_FILE_REQUIRED')) . '"' : '');
			?>
			<div class="dharma-form__field dharma-form__field--file mb-3">
				<?php if ($field['fileMode'] === 'dropzone') :
					$dzTitle = $field['label'] !== '' ? $field['label'] : Text::_($field['multiple'] ? 'MOD_DHARMA_FORM_FILE_DROP_MULTI' : 'MOD_DHARMA_FORM_FILE_DROP');
					$dzHint  = $field['placeholder'] !== '' && $field['placeholder'] !== $field['label'] ? $field['placeholder'] : Text::_('MOD_DHARMA_FORM_FILE_DROP_HINT');
					?>
					<div class="dharma-form__upload dharma-form__dropzone">
						<span class="dharma-form__dz-icon" aria-hidden="true">
							<svg viewBox="0 0 24 24" focusable="false"><path d="M12 16V4m0 0L7.5 8.5M12 4l4.5 4.5M4 15v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/></svg>
						</span>
						<span class="dharma-form__dz-title rf-upload-button-text"><?php echo $e($dzTitle); ?></span>
						<span class="dharma-form__dz-hint"><?php echo $e($dzHint); ?></span>
						<input <?php echo $fileAttr; ?> aria-label="<?php echo $e($dzTitle); ?>">
					</div>
				<?php else :
					$caption = $field['label'] !== '' ? $field['label'] : Text::_('MOD_DHARMA_FORM_FILE_BUTTON');
					?>
					<div class="dharma-form__upload dharma-form__upload-button">
						<button type="button" class="<?php echo $e(trim((string) $params->get('file_button_class', '') . ' dharma-form__upload-btn rf-upload-button-text')); ?>" tabindex="-1"><?php echo $e($caption); ?></button>
						<input <?php echo $fileAttr; ?> aria-label="<?php echo $e($caption); ?>">
					</div>
				<?php endif; ?>
				<?php if ($field['list']) : ?><div class="rf-filenames-list dharma-form__files"></div><?php endif; ?>
				<?php echo $errorEl($field); ?>
			</div>
			<?php continue;
		endif;

		if ($field['type'] === 'note') : ?>
			<div class="dharma-form__note"><?php echo $field['html']; ?></div>
			<?php continue;
		endif;

		if ($field['type'] === 'consent') : ?>
			<div class="dharma-form__field dharma-form__field--consent mb-3">
				<label class="dharma-form__consent">
					<input type="checkbox" name="<?php echo $e($field['name']); ?>" id="<?php echo $e($inputId); ?>" value="<?php echo $e(Text::_('JYES')); ?>" class="form-check-input<?php echo $field['required'] ? ' required' : ''; ?>"<?php echo $field['required'] ? ' required' : ''; ?>>
					<span><?php echo $field['html']; ?></span>
				</label>
				<?php echo $errorEl($field); ?>
			</div>
			<?php continue;
		endif;

		// Радио и чекбоксы: группа в fieldset с заголовком-легендой.
		if ($field['type'] === 'radio' || $field['type'] === 'checkboxes') :
			$isRadio = $field['type'] === 'radio';
			$boxName = $field['name'] . ($isRadio ? '' : '[]');
			?>
			<div class="dharma-form__field dharma-form__field--<?php echo $e($field['type']); ?> mb-3"<?php echo !$isRadio && $field['required'] ? ' data-dharma-group-required="' . $e($field['errorText']) . '"' : ''; ?>>
				<fieldset class="dharma-form__group">
					<?php if ($field['label'] !== '') : ?>
						<legend class="dharma-form__label"><?php echo $e($field['label']); ?><?php echo $field['required'] ? $star : ''; ?></legend>
					<?php endif; ?>
					<?php foreach ($field['options'] as $n => $option) : ?>
						<label class="dharma-form__option">
							<input type="<?php echo $isRadio ? 'radio' : 'checkbox'; ?>" name="<?php echo $e($boxName); ?>" id="<?php echo $e($inputId . '_' . $n); ?>" value="<?php echo $e($option['value']); ?>" class="form-check-input"<?php echo $isRadio && $field['required'] ? ' required' : ''; ?>>
							<span><?php echo $e($option['label']); ?></span>
						</label>
					<?php endforeach; ?>
				</fieldset>
				<?php echo $errorEl($field); ?>
			</div>
			<?php continue;
		endif;

		$class  = ($field['type'] === 'select' ? 'form-select' : 'form-control') . ($field['required'] ? ' required' : '');
		$common = 'name="' . $e($field['name']) . '" id="' . $e($inputId) . '" class="' . $class . '"'
			. ($field['type'] !== 'select' && $field['placeholder'] !== '' ? ' placeholder="' . $e($field['placeholder']) . '"' : '')
			. ($field['required'] ? ' required' : '');
		?>
		<div class="dharma-form__field dharma-form__field--<?php echo $e($field['type']); ?> mb-3">
			<?php if ($showLabels && $field['label'] !== '') : ?>
				<label class="dharma-form__label" for="<?php echo $e($inputId); ?>"><?php echo $e($field['label']); ?><?php echo $field['required'] ? $star : ''; ?></label>
			<?php endif; ?>

			<?php if ($field['type'] === 'textarea') : ?>
				<textarea <?php echo $common; ?> rows="<?php echo (int) $field['rows']; ?>"></textarea>
			<?php elseif ($field['type'] === 'select') : ?>
				<select <?php echo $common; ?>>
					<option value=""><?php echo $e($field['placeholder'] !== '' ? $field['placeholder'] : Text::_('MOD_DHARMA_FORM_SELECT_DEFAULT')); ?></option>
					<?php foreach ($field['options'] as $option) : ?>
						<option value="<?php echo $e($option['value']); ?>"><?php echo $e($option['label']); ?></option>
					<?php endforeach; ?>
				</select>
			<?php elseif ($field['type'] === 'phone') : ?>
				<input type="tel" <?php echo $common; ?> inputmode="tel" autocomplete="tel" data-dharma-phone<?php echo $field['mask'] !== '' ? ' data-mask="' . $e($field['mask']) . '"' : ''; ?><?php echo $field['pattern'] !== '' ? ' pattern="' . $e($field['pattern']) . '" title="' . $e(Text::_('MOD_DHARMA_FORM_PHONE_INCOMPLETE')) . '"' : ''; ?>>
			<?php elseif ($field['type'] === 'email') : ?>
				<input type="email" <?php echo $common; ?> autocomplete="email">
			<?php else : ?>
				<input type="text" <?php echo $common; ?>>
			<?php endif; ?>
			<?php echo $errorEl($field); ?>
		</div>
	<?php endforeach; ?>

	<?php if ($subject !== '') : ?><input type="hidden" name="rfSubject" value="<?php echo $e($subject); ?>"><?php endif; ?>
	<?php if ($target !== '') : ?><input type="hidden" name="rfTarget" value="<?php echo $e($target); ?>"><?php endif; ?>

	<div class="dharma-form__actions">
		<button type="button" class="<?php echo $e(trim($btnClass . ' rf-button-send')); ?>"<?php echo $rfText !== '' ? ' data-rf-text="' . $e($rfText) . '"' : ''; ?>>
			<?php echo $e($params->get('button_text', '')); ?>
			<?php if ((int) $params->get('button_arrow', 1) === 1) : ?><span class="ar-arrow" aria-hidden="true"></span><?php endif; ?>
		</button>
	</div>
</form>
<?php if ($after['action'] === 'message') : ?>
	<div class="dharma-form__thanks" hidden role="status">
		<div class="dharma-form__thanks-icon" aria-hidden="true">
			<svg viewBox="0 0 52 52" focusable="false">
				<circle class="dharma-form__thanks-ring" cx="26" cy="26" r="24"/>
				<path class="dharma-form__thanks-check" d="M14.5 27.5l8 8 15-17"/>
			</svg>
		</div>
		<?php if ($after['title'] !== '') : ?><h3 class="dharma-form__thanks-title"><?php echo $e($after['title']); ?></h3><?php endif; ?>
		<?php if ($after['message'] !== '') : ?><div class="dharma-form__thanks-text"><?php echo $after['message']; ?></div><?php endif; ?>
		<?php if ($mode === 'modal') : ?>
			<button type="button" class="<?php echo $e(trim($btnClass . ' dharma-form__thanks-close')); ?>" data-bs-dismiss="modal"><?php echo Text::_('JCLOSE'); ?></button>
		<?php endif; ?>
	</div>
<?php endif; ?>
