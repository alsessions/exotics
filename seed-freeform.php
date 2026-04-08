<?php
/**
 * Creates the Freeform "Contact" form with Name, Email, and Message fields.
 * Run via: ddev craft exec 'require("/var/www/html/seed-freeform.php");'
 */

use craft\helpers\StringHelper;
use Solspace\Freeform\controllers\api\FormsController;
use Solspace\Freeform\Events\Forms\PersistFormEvent;
use Solspace\Freeform\Fields\Implementations\EmailField;
use Solspace\Freeform\Fields\Implementations\TextField;
use Solspace\Freeform\Fields\Implementations\TextareaField;
use Solspace\Freeform\Form\Types\Regular;
use Solspace\Freeform\Freeform;
use Solspace\Freeform\Library\Helpers\SitesHelper;
use yii\base\Event;

// In console context there's no logged-in user; impersonate the admin so
// FormPersistence can set createdByUserId.
$adminUser = \Craft::$app->getUsers()->getUserById(1);
if ($adminUser) {
    \Craft::$app->getUser()->setIdentity($adminUser);
}

// Check if form already exists
$existing = Freeform::getInstance()->forms->getFormByHandle('contact');
if ($existing) {
    echo "Contact form already exists (id: {$existing->id}). Skipping.\n";
    return;
}

// Field definitions
$fieldsData = [
    [
        'type'      => 'Text',
        'label'     => 'Full Name',
        'handle'    => 'fullName',
        'required'  => true,
        'placeholder' => 'Your name',
        'pageIndex' => 0,
    ],
    [
        'type'      => 'Email',
        'label'     => 'Email Address',
        'handle'    => 'emailAddress',
        'required'  => true,
        'placeholder' => 'you@example.com',
        'pageIndex' => 0,
    ],
    [
        'type'      => 'Textarea',
        'label'     => 'Message',
        'handle'    => 'message',
        'required'  => true,
        'placeholder' => 'How can we help you?',
        'rows'      => 5,
        'pageIndex' => 0,
    ],
];

// Type class map
$typeMap = [
    'Text'     => TextField::class,
    'Email'    => EmailField::class,
    'Textarea' => TextareaField::class,
];

// Full-width field types (one per row)
$fullWidthTypes = ['Textarea'];

// Compute row indices: full-width types get their own row; others share 2 per row
function computeRowIndices(array $fields, array $fullWidthTypes): array
{
    $indices   = [];
    $currentRow = 0;
    $slotsUsed  = 0;
    foreach ($fields as $f) {
        $isFullWidth = in_array($f['type'], $fullWidthTypes, true);
        if ($isFullWidth) {
            if ($slotsUsed > 0) { ++$currentRow; $slotsUsed = 0; }
            $indices[] = $currentRow++;
            $slotsUsed = 0;
        } else {
            if ($slotsUsed >= 2) { ++$currentRow; $slotsUsed = 0; }
            $indices[] = $currentRow;
            ++$slotsUsed;
        }
    }
    return $indices;
}

$rowIndices = computeRowIndices($fieldsData, $fullWidthTypes);

// Build layout
$pageLayoutUid = StringHelper::UUID();
$pageUid       = StringHelper::UUID();
$pageButtons   = (object)[
    'layout'         => 'submit',
    'submitLabel'    => 'Send Message',
    'back'           => false,
    'backLabel'      => 'Back',
    'save'           => false,
    'saveLabel'      => 'Save',
    'saveRedirectUrl' => '',
];

// Create rows
$maxRow  = max($rowIndices);
$rowUids = [];
$rows    = [];
for ($i = 0; $i <= $maxRow; $i++) {
    $uid = StringHelper::UUID();
    $rowUids[$i] = $uid;
    $rows[] = (object)[
        'uid'       => $uid,
        'layoutUid' => $pageLayoutUid,
        'order'     => $i,
    ];
}

// Create fields
$propertyProvider = Freeform::getInstance()->propertyProvider ?? null;
$fields = [];
foreach ($fieldsData as $idx => $fieldData) {
    $typeClass  = $typeMap[$fieldData['type']] ?? TextField::class;
    $rowUid     = $rowUids[$rowIndices[$idx]];

    // Build properties from defaults
    $properties = [];
    if ($propertyProvider) {
        $editableProps = $propertyProvider->getEditableProperties($typeClass);
        foreach ($editableProps as $prop) {
            $properties[$prop->handle] = $fieldData[$prop->handle] ?? $prop->value;
        }
    }
    // Merge in any extra field data (label, handle, required, placeholder, rows, etc.)
    foreach (['label', 'handle', 'required', 'placeholder', 'rows', 'instructions'] as $key) {
        if (isset($fieldData[$key])) {
            $properties[$key] = $fieldData[$key];
        }
    }

    $fields[] = (object)[
        'uid'       => StringHelper::UUID(),
        'rowUid'    => $rowUid,
        'typeClass' => $typeClass,
        'order'     => $idx,
        'properties' => (object)$properties,
    ];
}

// Assemble full payload
$form = (object)[
    'uid'  => StringHelper::UUID(),
    'type' => Regular::class,
    'settings' => (object)[
        'general' => (object)[
            'name'               => 'Contact',
            'handle'             => 'contact',
            'type'               => Regular::class,
            'formattingTemplate' => '',
            'storeData'          => true,
            'sites'              => SitesHelper::getEditableSiteIds(),
            'description'        => '',
        ],
    ],
];

$layout = (object)[
    'pages'   => [
        (object)[
            'uid'       => $pageUid,
            'layoutUid' => $pageLayoutUid,
            'order'     => 0,
            'label'     => 'Page 1',
            'buttons'   => $pageButtons,
        ],
    ],
    'layouts' => [(object)['uid' => $pageLayoutUid]],
    'rows'    => $rows,
    'fields'  => $fields,
];

$payload = (object)['form' => $form, 'layout' => $layout];
$event   = new PersistFormEvent($payload);

Event::trigger(FormsController::class, FormsController::EVENT_CREATE_FORM, $event);
Event::trigger(FormsController::class, FormsController::EVENT_UPSERT_FORM, $event);
Event::trigger(FormsController::class, FormsController::EVENT_AFTER_SAVE_FORM, $event);

if ($event->hasErrors()) {
    $errors = $event->getResponseData()['errors'] ?? [];
    echo "ERROR creating form:\n";
    print_r($errors);
    return;
}

$createdForm = $event->getForm();
echo "Contact form created successfully. ID: {$createdForm->getId()}\n";
