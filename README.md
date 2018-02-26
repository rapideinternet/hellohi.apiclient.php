# mka.apiclient
PHP Client for the MKA API

## Setup
```
require_once __DIR__ . '/vendor/autoload.php'; 

use ApiClient\Client;
use ApiClient\Model;

Client::init(
	"https://api.staging.hellohi.nl/v1/oauth/token",
	"https://api.staging.hellohi.nl/v1",
	"your-oauth-client-id",
	"your-oauth-your-secret",
	"you@yourcompany.com",
	"your-password",
	"your-tenant-id"
);
```

## get threads with creator includes
```$threads = Model::all('threads', ['creator']);```

## get all threads magic with creator includes
```$threads = Model::threads(['creator']);```

## find a thread by id static
```$thread = Model::byId('threads', "mjkvxl5qmwza68b9");```

## get thread items magic
```$thread->all('items');```

## get thread_items magic
```$thread->items; //$thread->items();```

## find a thread_item by id
```$thread->byId('items', "mn9vlbrl4w8zjoqx");```

## get thread_items with includes
```$items = $thread->items(['participant', 'participant.person']);```

## update a thread
```
$thread = Model::byId('threads', "kyd9nprax5lajbv3");
$thread->update([
  'subject' => 'Some subject'
]);
```

## update a thread message with endpoint override
```
$thread->all('items')[0]
  ->setEndpoint('messages')
  ->update(['message' => 'Different messag']);
  ```
    
## create a thread
		$thread->create('threads', [
			'subject' => 'Rapportage',
			'company_id' => 'kyd9nprax5lajbv3',
	    'participants' => [
		 		[
				  'person_id' => '8bkgjnrmdrz63pvd',
			    'company_id' => 'kyd9nprax5lajbv3',
				  'is_admin' => 1
				]
			],
			'dossier_item_groups' => [
				[
					'dossier_item_group_template_id' => '8bkgjnrmdrz63pvd',
					'year' => 2017,
					'period' => 'q2'
				]
			],
			'message' => 'branker'
		]);

## delete a thread
```$thread->delete();```
