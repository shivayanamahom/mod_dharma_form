// Модуль "Конструктор форм": маска телефона, открытие модальных форм по
// селектору-триггеру и закрытие модалки после успешной отправки RadicalForm.
(() => {
  const DEFAULT_MASK = '+7 (###) ###-##-##';

  // Применяет маску: # — цифра, остальные символы — литералы. Литералы
  // выводятся только перед очередной цифрой, поэтому стирание работает.
  const applyMask = (value, mask) => {
    let digits = value.replace(/\D/g, '');

    if (!digits) {
      return '';
    }

    const firstHash = mask.indexOf('#');
    const lead = firstHash === -1 ? mask : mask.slice(0, firstHash);
    const prefix = lead.replace(/\D/g, '');

    // Цифры префикса (например, 7 из "+7 (") вводить повторно не нужно;
    // для российской маски 8 в начале считается за 7.
    if (prefix && digits.startsWith(prefix)) {
      digits = digits.slice(prefix.length);
    } else if (prefix === '7' && digits[0] === '8') {
      digits = digits.slice(1);
    }

    if (!digits) {
      return lead.replace(/[^\p{L}\p{N}]+$/u, '');
    }

    let result = '';
    let i = 0;

    for (const char of mask) {
      if (i >= digits.length) {
        break;
      }

      if (char === '#') {
        result += digits[i++];
      } else {
        result += char;
      }
    }

    return result;
  };

  const initPhone = (input) => {
    const mask = input.dataset.mask?.trim() || DEFAULT_MASK;
    const placeholder = input.getAttribute('placeholder') || '';

    input.maxLength = mask.length;

    input.addEventListener('focus', () => {
      if (!input.value) {
        input.placeholder = mask.replace(/#/g, '_');
      }
    });

    input.addEventListener('input', () => {
      input.value = applyMask(input.value, mask);
    });

    input.addEventListener('blur', () => {
      if (!input.value) {
        input.placeholder = placeholder;
      }
    });
  };

  // Область перетаскивания: подсветка при наведении файла. Сам файл принимает
  // прозрачный input поверх неё — браузер вызывает на нём change.
  const initDropzone = (zone) => {
    ['dragenter', 'dragover'].forEach((type) => {
      zone.addEventListener(type, () => zone.classList.add('is-dragover'));
    });

    ['dragleave', 'drop'].forEach((type) => {
      zone.addEventListener(type, () => zone.classList.remove('is-dragover'));
    });
  };

  // Обязательный файл: RadicalForm очищает input после загрузки, поэтому обычный
  // required не годится. Ставим собственную ошибку, пока в списке нет файлов.
  const initFileRequired = (input) => {
    const form = input.closest('form');

    if (!form) {
      return;
    }

    const update = () => {
      const uploaded = form.querySelectorAll('.rf-filenames-list [data-name]').length > 0;

      input.setCustomValidity(uploaded ? '' : input.dataset.dharmaFileRequired);
    };

    update();
    new MutationObserver(update).observe(form, { childList: true, subtree: true });
  };

  // Классы ошибки задаёт плагин RadicalForm (настройка "dangerclass").
  const dangerClasses = () => (window.RadicalForm?.DangerClass || 'is-invalid').trim().split(/\s+/);

  // Скрытые поля: адрес и заголовок страницы подставляются на лету, чтобы
  // работало и при кэшировании страниц, и после повторной отправки.
  const fillHidden = (form) => {
    form.querySelectorAll('input[data-dharma-hidden]').forEach((input) => {
      if (input.dataset.dharmaHidden === 'url') {
        input.value = window.location.href;
      } else if (input.dataset.dharmaHidden === 'title') {
        input.value = document.title;
      } else {
        input.value = input.dataset.value || '';
      }
    });
  };

  // Радио и чекбоксы: RadicalForm снимает подсветку только с изменённого
  // элемента, а ошибка относится ко всей группе. Для чекбоксов "хотя бы один"
  // проверяем сами.
  const initGroup = (field) => {
    const boxes = [...field.querySelectorAll('input[type="checkbox"]')];
    const message = field.dataset.dharmaGroupRequired;

    const update = () => {
      const any = boxes.some((box) => box.checked);

      boxes.forEach((box) => box.setCustomValidity(message && !any ? message : ''));
    };

    field.addEventListener('change', () => {
      field.querySelectorAll('input').forEach((input) => input.classList.remove(...dangerClasses()));
      update();
    });

    update();
  };

  // Текст ошибки под полем: показываем, когда RadicalForm пометил поле невалидным.
  const isEmptyField = (field) => {
    const control = field.querySelector('input:not([type="hidden"]), select, textarea');

    if (!control) {
      return true;
    }

    if (control.type === 'file') {
      return !control.closest('form').querySelector('.rf-filenames-list [data-name]');
    }

    if (control.type === 'radio' || control.type === 'checkbox') {
      return !field.querySelector('input:checked');
    }

    return control.value.trim() === '';
  };

  const syncError = (field) => {
    const box = field.querySelector(':scope > .dharma-form__error');

    if (!box) {
      return;
    }

    const flag = dangerClasses()[0];
    const invalid = [...field.querySelectorAll('input, select, textarea')].some((control) => control.classList.contains(flag));

    box.textContent = invalid ? (isEmptyField(field) ? box.dataset.msgEmpty : box.dataset.msg) : '';
    box.hidden = !invalid;
    field.classList.toggle('has-error', invalid);
  };

  const initForm = (form) => {
    fillHidden(form);

    // Перед отправкой обновляем скрытые поля раньше обработчика плагина.
    form.addEventListener('click', (event) => {
      if (event.target.closest('.rf-button-send')) {
        fillHidden(form);
      }
    }, true);

    // Отправку выполняет RadicalForm по кнопке. У формы нет action и method, а
    // если в ней одно текстовое поле (например, только телефон), Enter запускает
    // штатную отправку браузера: данные уходят GET-запросом в адресную строку.
    form.addEventListener('submit', (event) => event.preventDefault());

    form.querySelectorAll('.dharma-form__field--radio, .dharma-form__field--checkboxes').forEach(initGroup);

    new MutationObserver((records) => {
      const fields = new Set(records.map((record) => record.target.closest?.('.dharma-form__field')).filter(Boolean));

      fields.forEach(syncError);
    }).observe(form, { attributes: true, attributeFilter: ['class'], subtree: true });
  };

  // Цель аналитики: Яндекс.Метрика, GA4 (gtag) и GTM (dataLayer) — что есть на сайте.
  const sendGoal = (form) => {
    const goal = form.dataset.goal;

    if (!goal) {
      return;
    }

    const counter = Number(form.dataset.goalYm);

    if (counter && typeof window.ym === 'function') {
      window.ym(counter, 'reachGoal', goal);
    }

    if (typeof window.gtag === 'function') {
      window.gtag('event', goal, { form_id: form.id });
    }

    if (Array.isArray(window.dataLayer)) {
      window.dataLayer.push({ event: goal, form_id: form.id });
    }
  };

  const showModal = (modal) => {
    if (window.bootstrap?.Modal) {
      window.bootstrap.Modal.getOrCreateInstance(modal).show();
    }
  };

  const initModal = (modal) => {
    // Переносим модалку в body, чтобы её не обрезали transform/overflow шапки.
    document.body.append(modal);

    const selector = modal.dataset.dharmaFormTrigger?.trim();

    if (selector) {
      document.addEventListener('click', (event) => {
        let trigger = null;

        try {
          trigger = event.target.closest(selector);
        } catch (error) {
          return;
        }

        if (!trigger) {
          return;
        }

        event.preventDefault();
        showModal(modal);
      });
    }
  };

  document.querySelectorAll('input[data-dharma-phone]').forEach(initPhone);
  document.querySelectorAll('.dharma-form-modal').forEach(initModal);
  document.querySelectorAll('.dharma-form__dropzone').forEach(initDropzone);
  document.querySelectorAll('.dharma-form').forEach(initForm);
  document.querySelectorAll('input[data-dharma-file-required]').forEach(initFileRequired);

  document.querySelectorAll('[data-dharma-form-open]').forEach((button) => {
    button.addEventListener('click', () => {
      const modal = document.querySelector(button.dataset.dharmaFormOpen);

      if (modal) {
        showModal(modal);
      }
    });
  });

  const showThanks = (form) => {
    const thanks = form.parentElement?.querySelector(':scope > .dharma-form__thanks');

    if (thanks) {
      form.hidden = true;
      thanks.hidden = false;
      form.closest('.modal-content, .mod-dharma-form')?.classList.add('is-sent');
    }
  };

  // Модалка закрыта — возвращаем пустую форму вместо благодарности.
  const resetForm = (modal) => {
    modal.querySelectorAll('.dharma-form').forEach((form) => {
      const thanks = form.parentElement?.querySelector(':scope > .dharma-form__thanks');

      if (thanks && !thanks.hidden) {
        thanks.hidden = true;
        form.hidden = false;
        form.reset();
        modal.querySelector('.modal-content')?.classList.remove('is-sent');
      }
    });
  };

  document.querySelectorAll('.dharma-form-modal').forEach((modal) => {
    modal.addEventListener('hidden.bs.modal', () => resetForm(modal));
  });

  const closeModal = (form, delay) => {
    const modal = form.closest('.dharma-form-modal');

    if (!modal || !window.bootstrap?.Modal) {
      return;
    }

    window.setTimeout(() => window.bootstrap.Modal.getInstance(modal)?.hide(), delay);
  };

  // После успешной отправки RadicalForm вызывает rfCall_2 (по умолчанию) или
  // rfCall_1/3 — оборачиваем их. Формы модуля ведут себя по настройкам
  // "После отправки", остальные формы на сайте идут как раньше.
  const wrapSuccessHandler = (name) => {
    const original = typeof window[name] === 'function' ? window[name] : null;

    if (original?.dharmaWrapped) {
      return;
    }

    const wrapped = function (message, here) {
      const form = here?.closest?.('.dharma-form');

      if (!form) {
        return original ? original.apply(this, arguments) : undefined;
      }

      const action = form.dataset.after || 'default';
      let result;

      // Для своего сообщения и перехода стандартный обработчик не вызываем:
      // иначе поверх выскочит alert/сообщение плагина.
      if (action === 'default' && original) {
        result = original.apply(this, arguments);
      }

      // Хук для аналитики: document.addEventListener('dharmaform:sent', ...)
      form.dispatchEvent(new CustomEvent('dharmaform:sent', {
        bubbles: true,
        detail: { message, form },
      }));

      sendGoal(form);

      if (action === 'redirect' && form.dataset.redirect) {
        // Если есть цель, даём счётчику долю секунды отправить данные до ухода со страницы.
        const delay = Math.max(Number(form.dataset.redirectDelay) || 0, form.dataset.goal ? 300 : 0);

        window.setTimeout(() => {
          window.location.href = form.dataset.redirect;
        }, delay);

        return result;
      }

      if (action === 'message') {
        showThanks(form);
      }

      if (form.dataset.close === '1') {
        closeModal(form, Number(form.dataset.closeDelay) || 0);
      }

      return result;
    };

    wrapped.dharmaWrapped = true;
    window[name] = wrapped;
  };

  ['rfCall_1', 'rfCall_2', 'rfCall_3'].forEach(wrapSuccessHandler);
})();
