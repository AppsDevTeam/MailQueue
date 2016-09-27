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

---

### 1.2.1 Using one and only `IMailer`

```neon
adtMailQueue:
	mailer: ADT\SparkPostApiMailer\Service\SparkPostApiMailer
```

### 1.2.2 Custom mailer

If you need to decide which mailer you want to use based on information
in your custom Queue entity, you can implement `ADT\MailQueue\Service\IMessenger`
interface. This interface has `send($entity)` method where `$entity` is your custom entity.

```neon
adtMailQueue:
	messenger: App\Model\QueueMailerMessenger # implements ADT\MailQueue\Service\IMessenger
```

Note: If you're getting `No service of type ... found.` exception, check that
you have registered that class as a service.

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