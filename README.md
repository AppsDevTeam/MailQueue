# MailQueue

Allows in-app mail queueing and delayed sending.

## 1.1 Installation

composer:
```bash
composer require adt/mail-queue
```

config.neon:
```neon
extensions:
	adtMailQueue: ADT\MailQueue\DI\MailQueueExtension
```

### 1.1.1 Using default Queue entity

Let Doctrine know about our entity:
```neon
doctrine:
	metadata:
		ADT\MailQueue\Entity: %vendorDir%/adt/mail-queue/src/Entity
```

### 1.1.2 Using custom Queue entity

Create your own entity that extends our abstract entity:
```php
namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class QueueEntity extends \ADT\MailQueue\Entity\AbstractMailQueueEntry {

	/**
	 * @ORM\Column(type="text")
	 */
	protected $customProperty;

}
```

Let us know about your custom entity:
```neon
adtMailQueue:
	queueEntityClass: App\Model\Entity\QueueEntity
```

To fill custom properties of your entity when enqueueing mail,
you can use second argument of `enqueue`:
```php
$this->mailQueueService->enqueue($mail, [
	'customProperty' => 'customValue'
]);
```

or

```php
$this->mailQueueService->enqueue($mail, function (QueueEntity $e) {
	$e->customProperty = 'customValue';
});
```

---

### 1.2.1 Using one and only `IMailer`

```neon
adtMailQueue:
	mailer: @sparkPostApiMailerService
```

### 1.2.2 Custom mailer

If you need to decide which mailer you want to use based on information
in your custom Queue entity, you can implement `ADT\MailQueue\Service\IMessenger`
interface. This interface has `send($entity)` method where `$entity` is your custom entity.

```neon
adtMailQueue:
	messenger: @queueMailerMessenger
```

---

### 1.3 Migration

Clear your `temp/cache` directory.

Generate migration and migrate:
```bash
php www/index.php migrations:diff
php www/index.php migrations:migrate
```

### 1.4 Processing enqueued messages

Use predefined console command:
```bash
php www/index.php mail-queue:process
```

or get `ADT\MailQueue\Services\QueueService` from DI container and call:
```php
$queueService->process()
```

### 1.5 Send error handler

If you need to handle send error, you can set:
```neon
adtMailQueue:
	sendErrorHandler: @ErrorHandlerClass::handlerMethod
```

Handler method receives queue entry entity and exception generated on send.

### 1.6 Queue drained event

If you need notification when queue is drained, you can set:
```neon
adtMailQueue:
	onQueueDrained: @EventHandlerClass::handlerMethod
```

Event handler receives instance of `OutputInterface` if available, `NULL` otherwise.

## 2.1 Configuration

```neon
adtMailQueue:
    messenger: #or mailer
    queueEntityClass: #default Entity\MailQueueEntry::class,
    autowireMailer: false
    sendErrorHandler: null
    onQueueDrained: null
    lockTimeout: 600
    limit: 1000 #how many emails send
    tempDir: %tempDir%
    backgroundQueueService: @ADT\BackgroundQueue\Service
    backgroundQueueCallbackName: mailSending
```

