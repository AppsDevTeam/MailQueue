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

### 1.2.1 Using one and only `IMailer`

```neon
adtMailQueue:
	mailer: ADT\SparkPostApiMailer\Service\SparkPostApiMailer
```

### 1.2.2 Multiple `IMailer` by Queue entity

Allows you to choose whatever mailer you want to use in the run-time.

```neon
adtMailQueue:
	messenger: App\Model\QueueMailerMessenger # implements ADT\MailQueue\Service\IMessenger
```


---

### 1.3 Migration

Clear your `temp/cache` directory.

Generate migration and migrate:
```bash
php www/index.php migrations:diff
php www/index.php migrations:migrate
```
