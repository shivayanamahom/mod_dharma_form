<?php

namespace Joomla\Module\DharmaForm\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\Registry\Registry;

/**
 * Подготовка полей формы из параметров модуля.
 */
class FormHelper
{
	/**
	 * Маска телефона по умолчанию (совпадает с DEFAULT_MASK в media/js/form.js).
	 */
	private const DEFAULT_MASK = '+7 (###) ###-##-##';

	/**
	 * Лимиты: защита от случайно (или намеренно) раздутых настроек.
	 */
	private const MAX_FIELDS = 50;

	private const MAX_MASK = 40;

	/**
	 * Типы полей, доступные в конструкторе.
	 */
	private const TYPES = ['text', 'email', 'textarea', 'phone', 'select', 'radio', 'checkboxes', 'file', 'hidden', 'consent', 'note'];

	/**
	 * Типы, чьё имя можно получить транслитерацией названия.
	 */
	private const TRANSLIT_TYPES = ['text', 'textarea', 'select', 'radio', 'checkboxes'];

	/**
	 * Имена скрытых полей по источнику значения.
	 */
	private const HIDDEN_NAMES = ['url' => 'page_url', 'title' => 'page_title', 'custom' => 'source'];

	/**
	 * Имена по умолчанию для поля без своего имени.
	 */
	private const DEFAULT_NAMES = [
		'text'     => 'name',
		'email'    => 'email',
		'textarea' => 'comment',
		'phone'    => 'phone',
		'select'     => 'choice',
		'radio'      => 'choice',
		'checkboxes' => 'choices',
		'file'       => 'file',
		'hidden'     => 'hidden_field',
		'consent'    => 'agree',
		'note'       => 'note',
	];

	/**
	 * Имена, которые RadicalForm использует сам — для полей они запрещены.
	 */
	private const RESERVED = [
		'option', 'plugin', 'group', 'format', 'module', 'template', 'method',
		'rfjsaction', 'rfjschallenge', 'rfjsproof', 'rfjspermit',
		'rfsubject', 'rftarget',
	];

	/**
	 * Возвращает нормализованный список полей: только известные типы,
	 * безопасные уникальные имена (атрибут name обязателен для RadicalForm).
	 *
	 * @param   Registry  $params  Параметры модуля
	 *
	 * @return  array<int, array<string, mixed>>
	 */
	public function getFields(Registry $params): array
	{
		$rows   = \array_slice((array) $params->get('fields', []), 0, self::MAX_FIELDS);
		$fields = [];
		$used   = [];
		$hasList = false;

		foreach ($rows as $row) {
			$row  = (array) $row;
			$type = (string) ($row['type'] ?? 'text');

			if (!\in_array($type, self::TYPES, true)) {
				continue;
			}

			// Согласие без текста выводить нечего.
			if ($type === 'consent' && $this->getConsentHtml($row) === '') {
				continue;
			}

			// Текстовый блок между полями: только безопасный HTML, поля у него нет.
			if ($type === 'note') {
				$noteHtml = $this->cleanHtml(trim((string) ($row['text'] ?? '')));

				if ($noteHtml !== '') {
					$fields[] = ['type' => 'note', 'name' => '', 'label' => '', 'placeholder' => '', 'required' => false, 'html' => $noteHtml];
				}

				continue;
			}

			$isList  = \in_array($type, ['select', 'radio', 'checkboxes'], true);
			$options = $isList ? $this->getOptions((string) ($row['options'] ?? '')) : [];

			// Список без вариантов выводить нечего.
			if ($isList && $options === []) {
				continue;
			}

			$source   = \in_array($row['hidden_source'] ?? 'url', ['url', 'title', 'custom'], true) ? (string) ($row['hidden_source'] ?? 'url') : 'url';
			$fallback = $type === 'hidden' ? self::HIDDEN_NAMES[$source] : '';

			$label = $type === 'hidden' ? '' : trim((string) ($row['label'] ?? ''));
			$name  = $this->makeName((string) ($row['name'] ?? ''), $label, $type, $used, $fallback);
			$error = trim((string) ($row['error'] ?? ''));

			$used[$name] = true;

			$placeholder = trim((string) ($row['placeholder'] ?? ''));

			$fields[] = [
				'type'        => $type,
				'name'        => $name,
				'label'       => $label,
				'placeholder' => $placeholder !== '' ? $placeholder : $label,
				'required'    => $type !== 'hidden' && (int) ($row['required'] ?? 0) === 1,
				'mask'        => $type === 'phone' ? $this->getMask((string) ($row['mask'] ?? '')) : '',
				'pattern'     => $type === 'phone' ? $this->getMaskPattern($this->getMask((string) ($row['mask'] ?? ''))) : '',
				'html'        => $type === 'consent' ? $this->getConsentHtml($row) : '',
				'fileMode'    => ($row['file_mode'] ?? 'button') === 'dropzone' ? 'dropzone' : 'button',
				'multiple'    => (int) ($row['file_multiple'] ?? 0) === 1,
				'accept'      => $type === 'file' ? $this->getAccept((string) ($row['file_accept'] ?? '')) : '',
				// RadicalForm пишет список файлов в первый .rf-filenames-list формы.
				'list'        => $type === 'file' && !$hasList,
				'rows'        => max(1, min(20, (int) ($row['rows'] ?? 3))),
				'options'     => $options,
				'source'      => $source,
				'value'       => $type === 'hidden' ? trim((string) ($row['hidden_value'] ?? '')) : '',
				'errorEmpty'  => $error !== '' ? $error : $this->getErrorText($type, true),
				'errorText'   => $error !== '' ? $error : $this->getErrorText($type, false),
			];

			if ($type === 'file') {
				$hasList = true;
			}
		}

		return $fields;
	}

	/**
	 * Собирает безопасное уникальное имя поля: введённое имя, а если его нет —
	 * транслитерация названия (только для текста и текстовой области), иначе —
	 * имя по типу поля: phone и email нужны RadicalForm под стандартными именами.
	 *
	 * @param   string  $name   Введённое имя
	 * @param   string  $label  Название поля
	 * @param   string  $type   Тип поля
	 * @param   array   $used      Уже занятые имена
	 * @param   string  $fallback  Имя по умолчанию вместо стандартного для типа
	 *
	 * @return  string
	 */
	private function makeName(string $name, string $label, string $type, array $used, string $fallback = ''): string
	{
		$name = $this->slug($name);

		if ($name === '' && \in_array($type, self::TRANSLIT_TYPES, true)) {
			$name = $this->slug($this->transliterate($label));
		}

		if ($name === '') {
			$name = $fallback !== '' ? $fallback : self::DEFAULT_NAMES[$type];
		}

		if (\in_array($name, self::RESERVED, true)) {
			$name = 'f_' . $name;
		}

		$base = $name;
		$i    = 2;

		while (isset($used[$name])) {
			$name = $base . '_' . $i++;
		}

		return $name;
	}

	/**
	 * Оставляет только латиницу, цифры и "_"; пробелы и дефисы становятся "_".
	 *
	 * @param   string  $value  Исходная строка
	 *
	 * @return  string
	 */
	private function slug(string $value): string
	{
		$value = strtolower(preg_replace('/[\s\-]+/u', '_', trim($value)) ?? '');
		$value = preg_replace('/[^a-z0-9_]/', '', $value) ?? '';
		$value = preg_replace('/_+/', '_', $value) ?? '';

		// Имя не должно начинаться с цифры и состоять из одних подчёркиваний.
		$value = trim($value, '_');

		return $value !== '' && ctype_digit($value[0]) ? 'f_' . $value : $value;
	}

	/**
	 * Транслитерация кириллицы в латиницу.
	 *
	 * @param   string  $value  Исходная строка
	 *
	 * @return  string
	 */
	private function transliterate(string $value): string
	{
		return strtr(mb_strtolower($value), [
			'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
			'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
			'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
			'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '',
			'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
		]);
	}

	/**
	 * Регулярное выражение для атрибута pattern: номер должен быть введён
	 * маской целиком. Каждый "#" — цифра, остальные символы — литералы.
	 *
	 * @param   string  $mask  Маска из настроек (пусто — маска по умолчанию)
	 *
	 * @return  string  Пустая строка, если в маске нет ни одного "#"
	 */
	private function getMaskPattern(string $mask): string
	{
		$mask = trim($mask) !== '' ? trim($mask) : self::DEFAULT_MASK;

		if (!str_contains($mask, '#')) {
			return '';
		}

		$pattern = '';

		foreach (mb_str_split($mask) as $char) {
			// Экранируем только спецсимволы regexp: "\-" вне класса в режиме u/v недопустим.
			$pattern .= $char === '#' ? '\\d' : (str_contains('^$\\.*+?()[]{}|/', $char) ? '\\' . $char : $char);
		}

		return $pattern;
	}

	/**
	 * Что делать после успешной отправки.
	 *
	 * @param   Registry  $params  Параметры модуля
	 * @param   string    $mode    Режим формы: simple|modal
	 *
	 * @return  array<string, mixed>
	 */
	public function getAfterSettings(Registry $params, string $mode): array
	{
		$action = (string) $params->get('after_action', 'default');
		$action = \in_array($action, ['default', 'message', 'redirect'], true) ? $action : 'default';
		$url    = $action === 'redirect' ? $this->getRedirectUrl($params) : '';

		// Переход без адреса не имеет смысла — оставляем стандартное поведение.
		if ($action === 'redirect' && $url === '') {
			$action = 'default';
		}

		return [
			'action'        => $action,
			'title'         => trim((string) $params->get('after_title', '')),
			'message'       => $this->cleanHtml(trim((string) $params->get('after_message', ''))),
			'redirect'      => $url,
			'redirectDelay' => max(0, min(60, (int) $params->get('after_redirect_delay', 0))),
			'close'         => $mode === 'modal' && $action !== 'redirect' && (int) $params->get('after_close', 1) === 1,
			'closeDelay'    => max(0, min(60, (int) $params->get('after_close_delay', 3))),
		];
	}

	/**
	 * Адрес перехода: свой адрес (только http(s):// или относительный) важнее
	 * пункта меню.
	 *
	 * @param   Registry  $params  Параметры модуля
	 *
	 * @return  string  Пустая строка, если адрес не задан или небезопасен
	 */
	private function getRedirectUrl(Registry $params): string
	{
		$url = trim((string) $params->get('after_redirect_url', ''));

		if ($url !== '') {
			return $this->safeUrl($url);
		}

		$itemId = (int) $params->get('after_redirect_menu', 0);

		return $itemId > 0 ? Route::_('index.php?Itemid=' . $itemId) : '';
	}

	/**
	 * Маска телефона: обрезаем до разумной длины.
	 *
	 * @param   string  $mask  Маска из настроек
	 *
	 * @return  string
	 */
	private function getMask(string $mask): string
	{
		return mb_substr(trim($mask), 0, self::MAX_MASK);
	}

	/**
	 * Безопасный адрес: только http(s)://, путь от корня (но не "//host" и не
	 * "/\host"), якорь, mailto: и tel:. Пробелы, обратные слеши и управляющие
	 * символы запрещены — браузеры превращают "/\host" во внешний адрес.
	 *
	 * @param   string  $url  Введённый адрес
	 *
	 * @return  string  Пустая строка, если адрес небезопасен
	 */
	public function safeUrl(string $url): string
	{
		$url = trim($url);

		if ($url === '' || preg_match('/[\x00-\x20\x7f\\\\]/u', $url)) {
			return '';
		}

		return preg_match('~^(https?://[^/]|/(?![/\\\\])|#|mailto:.|tel:[+\d()-]+$)~i', $url) ? $url : '';
	}

	/**
	 * Очистка HTML из настроек перед выводом: разрешённые теги и атрибуты,
	 * ссылки только с безопасным адресом. Фильтр при сохранении в админке
	 * можно обойти (импорт, прямая запись в БД), поэтому чистим и при выводе.
	 *
	 * @param   string  $html  HTML из параметров модуля
	 *
	 * @return  string
	 */
	public function cleanHtml(string $html): string
	{
		if (trim($html) === '') {
			return '';
		}

		$filter = InputFilter::getInstance(
			['a', 'b', 'strong', 'i', 'em', 'u', 'br', 'p', 'span', 'small', 'ul', 'ol', 'li', 'h3', 'h4', 'div'],
			['href', 'target', 'rel', 'class', 'title'],
			InputFilter::ONLY_ALLOW_DEFINED_TAGS,
			InputFilter::ONLY_ALLOW_DEFINED_ATTRIBUTES
		);

		$html = $filter->clean($html, 'html');

		// Второй рубеж: любой href должен пройти строгий белый список.
		return (string) preg_replace_callback(
			'~\shref="([^"]*)"~i',
			fn (array $m): string => $this->safeUrl(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8')) !== '' ? $m[0] : '',
			$html
		);
	}

	/**
	 * Варианты списка: по одному в строке, "значение|подпись" или просто подпись.
	 *
	 * @param   string  $text  Содержимое textarea
	 *
	 * @return  array<int, array{value: string, label: string}>
	 */
	private function getOptions(string $text): array
	{
		$options = [];

		foreach (preg_split('/\R/u', $text) ?: [] as $line) {
			$line = trim($line);

			if ($line === '') {
				continue;
			}

			[$value, $label] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');

			$label = $label !== '' ? $label : $value;
			$value = $value !== '' ? $value : $label;

			if ($label !== '') {
				$options[] = ['value' => $value, 'label' => $label];
			}

			if (\count($options) >= 100) {
				break;
			}
		}

		return $options;
	}

	/**
	 * Текст ошибки под полем по умолчанию.
	 *
	 * @param   string  $type   Тип поля
	 * @param   bool    $empty  true — текст для пустого поля, false — для неверного значения
	 *
	 * @return  string
	 */
	private function getErrorText(string $type, bool $empty): string
	{
		return Text::_(match ($type) {
			'email'           => $empty ? 'MOD_DHARMA_FORM_ERR_REQUIRED' : 'MOD_DHARMA_FORM_ERR_EMAIL',
			'phone'           => $empty ? 'MOD_DHARMA_FORM_ERR_REQUIRED' : 'MOD_DHARMA_FORM_ERR_PHONE',
			'file'            => 'MOD_DHARMA_FORM_ERR_FILE',
			'select', 'radio' => 'MOD_DHARMA_FORM_ERR_CHOICE',
			'checkboxes'      => 'MOD_DHARMA_FORM_ERR_CHOICES',
			'consent'         => 'MOD_DHARMA_FORM_ERR_CONSENT',
			default           => 'MOD_DHARMA_FORM_ERR_REQUIRED',
		});
	}

	/**
	 * Цель аналитики формы.
	 *
	 * @param   Registry  $params  Параметры модуля
	 *
	 * @return  array{name: string, ym: int}
	 */
	public function getGoal(Registry $params): array
	{
		$name = preg_replace('/[^\w.:-]/u', '', trim((string) $params->get('goal_name', ''))) ?? '';

		return ['name' => $name, 'ym' => $name !== '' ? max(0, (int) $params->get('goal_ym_id', 0)) : 0];
	}

	/**
	 * Атрибут accept: расширения, MIME-типы и маски, остальное отбрасывается.
	 *
	 * @param   string  $value  Введённая строка, например ".pdf, .jpg, image/*"
	 *
	 * @return  string
	 */
	private function getAccept(string $value): string
	{
		$result = [];

		foreach (preg_split('/[\s,;]+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $item) {
			if (preg_match('~^(\.[a-z0-9]+|[a-z]+/[a-z0-9.+*-]+)$~i', $item)) {
				$result[] = strtolower($item);
			}
		}

		return implode(',', array_unique($result));
	}

	/**
	 * CSS-селектор открывающих элементов из введённых классов: "callback, order"
	 * или ".callback .order" превращаются в ".callback, .order".
	 *
	 * @param   Registry  $params  Параметры модуля
	 *
	 * @return  string  Пустая строка, если классов нет
	 */
	public function getTriggerSelector(Registry $params): string
	{
		$classes = preg_split('/[\s,]+/', (string) $params->get('trigger_class', ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
		$result  = [];

		foreach ($classes as $class) {
			$class = preg_replace('/[^\w-]/u', '', ltrim($class, '.')) ?? '';

			if ($class !== '') {
				$result[] = '.' . $class;
			}
		}

		return implode(', ', array_unique($result));
	}

	/**
	 * Текст согласия: {link} заменяется ссылкой; если метки нет, а текст
	 * ссылки задан — ссылка дописывается в конец.
	 *
	 * @param   array  $row  Строка конструктора
	 *
	 * @return  string  Готовый (безопасный) HTML
	 */
	private function getConsentHtml(array $row): string
	{
		$text = $this->cleanHtml(trim((string) ($row['text'] ?? '')));
		$link = trim((string) ($row['link_text'] ?? ''));
		$url  = trim((string) ($row['link_url'] ?? ''));

		// Разрешены только безопасные адреса, javascript:, //host и подобное отбрасываем.
		$url = $this->safeUrl($url);

		$anchor = '';

		if ($link !== '') {
			$safeLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
			$anchor   = $url !== ''
				? '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">' . $safeLink . '</a>'
				: $safeLink;
		}

		if (str_contains($text, '{link}')) {
			return str_replace('{link}', $anchor, $text);
		}

		return trim($text . ' ' . $anchor);
	}
}
