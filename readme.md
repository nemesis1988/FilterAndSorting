### Filter And Sorting Trait

#### Подключение

Подключите FilterAndSorting трейт к модели. Если вы хотите получать вложенные модели, нужно объявить метод extraFields(),

который будет возвращать список реляций, которые можно получить во вложениях. Как это использовать, будет описано ниже

```php
    class SomeModel extends Model
    {
        use FilterAndSorting;

        ...

        public function extraFields()
        {
            return ['someRelation'];
        }

        ...

        public function someRelation()
        {
            return $this->hasOne(SomeRelation::class);
        }
    }
```

#### Использование фильтра

Для фильтрации нужно использовать get параметр filter, в который помещается json с полями для фильтрации и параметрами.

Допустимые параметры:

isNull - не обязательный параметр, принимает true или false, добавляет к запросу AND $key IS NULL или AND $key IS NOT NULL

operation - логическая операция для выборки, принимает '>','<','>=','<=','<>','not in','in','like'

value - значение для выборки, может быть массивом, если используется "operation":"in" или "operation":"not in"

from и to - интервал. Могут работать по одиночке. Если приходят даты - они автоматически преобразуются в "компьютерный формат", так же можно передавать числа. Используются операторы >= и <=, т.е. значения from и to включаются в выборку.

Можно обращаться без параметров, а просто писать {"id":1}, тогда будет выполнен запрос WHERE id = 1 (AND подставится, если есть еще параметры выборки)

Примеры запросов:

/message?filter={"created_at":{"from":"2016-02-20","to":"2016-02-24 23:59:59"}, "id":{"operation":"not in", "value":[2,3,4]}}

/message?filter={"id":{"from":2,"to":5}}

/message?filter={"id":{"to":5}} и /message?filter={"id":{"operation":"<=","value":5}} - эквивалентны

/message?filter={"updated_at":{"isNull":true}}

/message?filter={"answer":{"operation":"like","value":"Partial search string"}} - псевдополнотекстовый поиск, добавляет услове вида: WHERE answer LIKE "%Partial search string%"

/message?filter={"answer":"Full search string"} - точный поиск по строке

Фильтр позволяет фильтровать по вложенным моделям:

/message?filter={"user.name":"asd"}

Вложенные модели должны быть разрешены в методе extraFields, фильтровать можно аналогично фильтру по текущей модели

#### Использование сортировки

Сортировать можно по текущей модели, или по вложенной. Для сортировки необходимо передавать get параметр sort.

По умолчанию идет сортировка ASC, если нужно сделать DESC - перед полем для сортировки нужно ставить "-"/

Пример сортировки:

/message?sort=id

/message?sort=-id

/message?sort=user.name

#### Получение вложенных моделей

Для получения вложенных моделей (реляций), нужно разрешить их в методе модели extraFields() и при запросе добавлять get параметр expand.

Пример запроса:

  /message?expand=user

Пример ответа:

  {
    "id": 1,
    "message": "some message",
    "user_id": 1,
    ...
    "user": {
        "id": 1,
        "name": "Some username",
        ...
    }
  }