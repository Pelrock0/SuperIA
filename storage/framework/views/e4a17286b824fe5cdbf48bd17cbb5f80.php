
<?php
    $allowsMultiple = $crud->guessIfFieldHasMultipleFromRelationType($column['relation_type']);
    switch($column['relation_type']) {
        case 'HasOne':
        case 'MorphOne': 
            $column['type'] =  isset($column['subfields']) ? 'repeatable' : 'text';
        break;
        case 'HasMany':
        case 'HasManyThrough':
        case 'MorphMany':
        case 'BelongsToMany':
        case 'MorphToMany':
            $column['type'] = isset($column['subfields']) ? 'repeatable' : ($allowsMultiple ? 'select_multiple' : 'select');
        break;
        case 'BelongsTo':
        case 'MorphTo':
        case 'HasOneThrough':
            $column['type'] = 'select';
        break;
        default: 
            $column['type'] = 'text';
    }
?>

<?php echo $__env->first(\Backpack\CRUD\ViewNamespaces::getViewPathsFor('columns', $column['type']), array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<?php /**PATH I:\proyectos\SuperIA\vendor\backpack\pro/resources/views/columns/relationship.blade.php ENDPATH**/ ?>