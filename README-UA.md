# JsonRpcBundle
![Ukraine](https://img.shields.io/badge/%D0%A1%D0%BB%D0%B0%D0%B2%D0%B0-%D0%A3%D0%BA%D1%80%D0%B0%D1%97%D0%BD%D1%96-yellow?labelColor=blue)

JSON-RPC 2.0 сервер для Symfony v.6.* і новіше

### Про цей пакет

Пакет для легкого створення API за допомогою JSON-RPC сервера  

>Робити RPC сервіси для сервіс-орієнтованої архітектури на Symfony ще ніколи не було так просто.

![License](https://img.shields.io/badge/license-MIT-green?labelColor=7b8185)
 ![Size](https://img.shields.io/github/repo-size/ufo-tech/json-rpc-bundle?label=Size%20of%20the%20repository)
 ![package_version](https://img.shields.io/github/v/tag/ufo-tech/json-rpc-bundle?color=blue&label=Latest%20Version&logo=Packagist&logoColor=white&labelColor=7b8185)
 ![fork](https://img.shields.io/github/forks/ufo-tech/json-rpc-bundle?color=green&logo=github&style=flat)

### Вимоги до оточення
![php_version](https://img.shields.io/packagist/dependency-v/ufo-tech/json-rpc-bundle/php?logo=PHP&logoColor=white)
 ![symfony_version](https://img.shields.io/packagist/dependency-v/ufo-tech/json-rpc-bundle/symfony/framework-bundle?label=Symfony&logo=Symfony&logoColor=white)
 ![symfony_version](https://img.shields.io/packagist/dependency-v/ufo-tech/json-rpc-bundle/symfony/serializer?label=SymfonySerializer&logo=Symfony&logoColor=white)
 ![symfony version](https://img.shields.io/packagist/dependency-v/ufo-tech/json-rpc-bundle/symfony/serializer?label=SymfonyCache&logo=Symfony&logoColor=white)

Цей пакет дозволяє легко створювати API за допомогою JSON-RPC сервера для Symfony v.6.* і більш нових версій. Він підходить для розробників, які хочуть швидко і ефективно інтегрувати RPC функціонал у свої проекти.
## Що нового в версія 10.0
1. **Підтримка версіонування API**  
   Реалізовано нативну підтримку версій API на рівні RPC‑методів. Дозволяє перевизначати або розширювати методи в нових версіях без дублювання попередньої логіки та зі зворотньою сумісністю для існуючих клієнтів.

1. **Нормалізація API-документації до актуальної версії OpenRPC**  
   - Генерація документації приведена у відповідність до актуальної специфікації OpenRPC. Покращено структуру схем, типізацію параметрів та сумісність із зовнішніми інструментами генерації клієнтів і документації.
   - Серед іншого, enum-типи автоматично визначаються та коректно відображаються у схемах документації, включаючи backed та кастомні enum-значення.

1. **Автоматичне перетворення параметрів запиту**  
   Додано автоматичну конвертацію вхідних параметрів RPC-запиту у DTO, Enum, Entity та інші типізовані об'єкти на основі сигнатури методу, що суттєво зменшує кількість ручного коду обробки запитів.

   - **Автопідключення сервісів у методи API** — підтримка інʼєкції сервісів безпосередньо в RPC-методи аналогічно контролерам Symfony через механізм autowire.
   - **Реалізація Doctrine ParamConverter** — автоматичне отримання сутностей Doctrine за ідентифікатором без необхідності ручних репозиторних викликів.

1. **Компіляція ServiceMap через Compiler Pass**  
   Формування карти RPC‑сервісів перенесено на етап компіляції контейнера Symfony, що дозволяє уникнути runtime‑рефлексії та значно покращує продуктивність виконання API.

1. **Внутрішній рефакторинг та виправлення помилок**  
   Проведено оптимізацію внутрішньої архітектури, спрощено життєвий цикл побудови ServiceMap, покращено стабільність роботи та усунено низку технічних обмежень попередніх версій.

### Основні переваги
- **Простота інтеграції:** Інтеграція пакету з вашим проектом є надзвичайно простою. Вам потрібно лише додати спеціальний інтерфейс до будь-якого існуючого класу, і він автоматично отримає можливість обробляти JSON-RPC запити.
- **Гнучкість:** Пакет забезпечує велику гнучкість при створенні API, дозволяючи розробникам легко розширювати і модифікувати поведінку сервера без втручання у вже існуючий код.
- **Ефективність:** Завдяки оптимізації викликів і використанню сучасних компонентів Symfony, пакет гарантує високу продуктивність при обробці запитів.

## [Документація](https://docs.ufo-tech.space/bin/view/docs/JsonRpcBundle/?language=uk)
Вичерпну документацію по встановленню, налаштуванню і використанню цієї бібліотеки ви знайдете на порталі документацій [UFO-Tech](https://docs.ufo-tech.space/?language=uk) (Universal Flexible Open Technologies)

[JsonRpcBundle](https://docs.ufo-tech.space/bin/view/docs/JsonRpcBundle/?language=uk)