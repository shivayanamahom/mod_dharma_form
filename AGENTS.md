# Правила и архитектура Dharma Form для ИИ-агентов (AGENTS.md)

Этот документ предназначен для ИИ-ассистентов (Claude, Antigravity, Codex, Cursor и др.) и разработчиков, работающих над кодовой базой модуля **Dharma Form** (`mod_dharma_form`).

---

## 1. Контекст и архитектура проекта

1. **Тип расширения:** Модуль сайта (Site Module) для **Joomla 5.2+ и 6.x** на современной сервисной архитектуре Joomla (`services/provider.php`).
2. **Назначение:** Конструктор гибких форм обратной связи прямо в админке с поддержкой модальных окон Bootstrap 5, масок телефона, согласий 152-ФЗ, загрузки файлов и отправки через RadicalForm.
3. **Пространство имён:** `Joomla\Module\DharmaForm`.
4. **Связка с плагином RadicalForm:** Модуль **только рендерит форму**. Приём данных, валидацию на бэкенде, защиту от спама, отправку писем и уведомления в Telegram выполняет системный плагин `plg_system_radicalform`.
5. **Репозиторий:** `shivayanamahom/mod_dharma_form` (ветка `main`).

---

## 2. Железные правила кодовой базы

### 2.1. Манифест `mod_dharma_form.xml`
- **Структура `<files>` и `<media>`:**
  Все папки (`src`, `services`, `tmpl`, `media/css`, `media/js`, `media/readme.html`) должны быть строго зарегистрированы.
- **Сервер обновлений:**
  Секция `<updateservers>` обязательна и ссылается на:
  ```xml
  <updateservers>
      <server type="extension" priority="1" name="Dharma Form Updates">https://raw.githubusercontent.com/shivayanamahom/mod_dharma_form/main/updates.xml</server>
  </updateservers>
  ```
- **Конструктор полей (`subform`):**
  Поля формы задаются через повторяемый `subform` с типами: `text`, `email`, `textarea`, `phone`, `select`, `radio`, `checkbox`, `hidden`, `file`, `consent`. При добавлении новых типов полей их обработка должна поддерживаться как в XML-форме, так и в `tmpl/_form.php` и `src/Helper/FormHelper.php`.

### 2.2. Шаблоны вывода (`tmpl/`)
- `tmpl/default.php`: Корневой шаблон. Проверяет наличие и активность системного плагина `plg_system_radicalform`. Если плагин выключен, выводит предупреждение для администратора. Рендерит форму на странице или внутри модального окна Bootstrap 5.
- `tmpl/_form.php`: Рендеринг разметки полей формы, кнопок отправки, чекбоксов согласий и honeypot/антиспам полей.

### 2.3. Документация в пакете (`media/readme.html`)
- Модуль включает в себя HTML-версию справки `media/readme.html`, генерируемую скриптом `tools/readme2html.py` из `README.md`.
- **Перед любой сборкой архива ОБЯЗАТЕЛЬНО запускать `python3 tools/readme2html.py`** (скрипт `tools/build.sh` делает это автоматически).

---

## 3. Регламент сборки и выпуска релизов

В репозитории настроен автоматический CI/CD workflow в `.github/workflows/release.yml`.

### Выпуск нового релиза:
1. **Контроль синтаксиса PHP:**
   ```bash
   find . -name "*.php" -exec /Applications/ServBay/package/php/8.4/current/bin/php -l {} +
   ```
2. **Повышение версии в манифесте:**
   Изменить `<version>X.Y.Z</version>` в `mod_dharma_form.xml`.
3. **Локальная проверка сборки:**
   ```bash
   bash tools/build.sh
   ```
   Скрипт пересоберёт `media/readme.html` и упакует `dist/mod_dharma_form-X.Y.Z.zip`.
4. **Коммит и пуш в `main`:**
   ```bash
   git add .
   git commit -m "Release vX.Y.Z"
   git push origin main
   ```
5. **Автоматический релиз на GitHub Actions:**
   GitHub Actions обнаружит новую версию в `mod_dharma_form.xml`, соберёт релизный zip, обновит `updates.xml` с SHA-256 и целевыми платформами Joomla 5/6, создаст тег `vX.Y.Z` и опубликует релиз.
