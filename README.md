### Автоматизация обмена данными о товарах и товарных предложениях из 1C в Wordpress

Этот плагин позволяет автоматизировать наполнение товарами сайта на Wordpress с установленным плагином Woocommerce.

### Особенности плагина
* Создает дополнительную таксономию склад
* Добавляет произвольные поля: Ед. изм, внешний код, налоговая ставка
* Внедряет дополнительные поля в интерфейс изменения товара woocommerce
* Возможность создавать новые товары и обновлять существующие, так же __только создавать__ или __только обновлять__
* Возможность выбора что обновлять у уже существующих товаров
* Возможность деактивировать, устанавливать статус "нет в наличии", удалять товары после полной выгрузки, а так же если у товара не указана цена или совсем нет товарного предложения.

### На данный момент не работает
* Нет поддержки вариативных товаров

### В ближайшее время планируется
* Дописать особенности этого плагина
* Не работает пометка удаления в новых версиях 1C УТ

### Протокол обмена данными с сайтом commerceML

[Документация по протоколу обмена](http://v8.1c.ru/edi/edi_stnd/131/)

##### 1. Начало сеанса (Авторизация)
Выгрузка данных начинается с того, что система "1С:Предприятие" отправляет http-запрос следующего вида:
_?type=catalog&mode=checkauth_  

##### 2. Уточнение параметров сеанса
Система запрашивает возможности сервера  
_?type=catalog&mode=init_
```
zip=yes|no - Сервер поддерживает Zip
file_limit=<число> - максимально допустимый размер файла в байтах для передачи за один запрос
```

### Структура проекта
```
ORM
    - Collection

Utilites
    - Error (Singleton)
    - Transaction (Singleton)
    - Request

Register
    - Plugin (Singleton)
    - Register

Actions
    - Parser
    - REST
    - Update

Model
    - Term (a)
        - Category
        - Developer
        - Warehouse
        - Attribute value
    - Attribute
    - Post (a?)
        - Offer
        - Product

```

#### How to work /exchange/ page
```
template_redirect > REST_Controller->do_exchange()

do_exchange() {
    // Switch mode between
    $this->checkauth()

    $this->init() {
        exit( "zip=yes\nfile_limit=\d" );
    }

    $this->file() {
        // Save posted file
        // Unzip getting file
        // @todo Move to backup
    }

    $this->import() {
        $Update->update_terms() {
        	$Parser->parse();

            Update::terms( $categories, $developers, $warehouses );
            Update::term_meta( $categories, $developers, $warehouses );
        }

        $Update->update_products()->update_offers();
        $Update->update_products_relationships()->update_offers_relationships();
    }

    $this->deactivate()

    $this->complete()
}
```
_Developed by me with support of the company: [SEO18](//seo18.ru)_
 
