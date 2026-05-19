@props([
    'cancelUrl' => null,
    'cancelLabel' => 'Cancel',
    'cancelType' => 'link',
    'cancelAttributes' => [],
    'showSubmit' => true,
    'submitLabel' => 'Save',
    'submitType' => 'submit',
    'submitDisabled' => false,
    'submitAttributes' => [],
    'deleteHref' => null,
    'deleteFormAction' => null,
    'deleteSubmit' => false,
    'deleteLabel' => 'Delete',
    'deleteMethod' => 'DELETE',
    'deleteConfirm' => null,
    'deleteDisabled' => false,
    'deleteAttributes' => [],
    'containerClass' => 'wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap',
    'mainGroupClass' => 'wb-flex wb-items-center wb-gap-3 wb-flex-wrap',
    'dangerGroupClass' => 'wb-flex wb-items-center wb-gap-3 wb-flex-wrap',
])

@include('webblocks-cms::components.admin.form-actions', get_defined_vars())
