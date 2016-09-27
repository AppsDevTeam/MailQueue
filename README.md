# MailQueue

Allows in-app mail queueing and delayed sending.

## Installation

composer:
```bash
composer require adt/mail-queue
```

config.neon:
```neon
extensions:
	adtMailQueue: ADT\MailQueue\DI\MailQueueExtension
```

### Using default Queue entity

Let Doctrine know about our entity:
```neon
doctrine:
	metadata:
		ADT\MailQueue\Entity: %vendorDir%/adt/mail-queue/src/Entity
```

### Using custom Queue entity

Create your own entity that extends our default entity:
```php
namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class QueueEntity extends \ADT\MailQueue\Entity\MailQueue {

	/**
	 * @ORM\Column(type="text")
	 */
	protected $customProperty;

}
```

---

Generate migration and migrate:
```bash
php www/index.php migrations:diff
php www/index.php migrations:migrate
```
