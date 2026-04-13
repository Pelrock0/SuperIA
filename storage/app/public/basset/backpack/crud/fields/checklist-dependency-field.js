function bpFieldInitChecklistDependencyElement(element) {

    var unique_name = element.data('entity');
    var dependencyJson = window[unique_name];
    var thisField = element;
    var handleCheckInput = function(el, field, dependencyJson) {
      let idCurrent = el.data('id');
      //add hidden field with this value
      let nameInput = field.find('.hidden_fields_primary').data('name');
      if(field.find('input.primary_hidden[value="'+idCurrent+'"]').length === 0) {
        let inputToAdd = $('<input type="hidden" class="primary_hidden" name="'+nameInput+'[]" value="'+idCurrent+'">');

        field.find('.hidden_fields_primary').append(inputToAdd);
        field.find('.hidden_fields_primary').find('input.primary_hidden[value="'+idCurrent+'"]').trigger('change');
      }
      $.each(dependencyJson[idCurrent], function(key, value){
        //check and disable secondies checkbox
        field.find('input.secondary_list[value="'+value+'"]').prop( "checked", true );
        field.find('input.secondary_list[value="'+value+'"]').prop( "disabled", true );
        field.find('input.secondary_list[value="'+value+'"]').attr('forced-select', 'true');
        //remove hidden fields with secondary dependency if was set
        var hidden = field.find('input.secondary_hidden[value="'+value+'"]');
        if(hidden)
          hidden.remove();
      });
    };
    
    thisField.find('div.hidden_fields_primary').children('input').first().on('CrudField:disable', function(e) {
        let input = $(e.target);
        input.parent().parent().find('input[type=checkbox]').attr('disabled', 'disabled');
        input.siblings('input').attr('disabled','disabled');
    });

    thisField.find('div.hidden_fields_primary').children('input').first().on('CrudField:enable', function(e) {
        let input = $(e.target);
        input.parent().parent().find('input[type=checkbox]').not('[forced-select]').removeAttr('disabled');
        input.siblings('input').removeAttr('disabled');
    });

    thisField.find('div.hidden_fields_secondary').children('input').first().on('CrudField:disable', function(e) {
        let input = $(e.target);
        input.parent().parent().find('input[type=checkbox]').attr('disabled', 'disabled');
        input.siblings('input').attr('disabled','disabled');
    });

    thisField.find('div.hidden_fields_secondary').children('input').first().on('CrudField:enable', function(e) {
        let input = $(e.target);
        input.parent().parent().find('input[type=checkbox]').not('[forced-select]').removeAttr('disabled');
        input.siblings('input').removeAttr('disabled');
    });

    thisField.find('.primary_list').each(function() {
      var checkbox = $(this);
      // re-check the secondary boxes in case the primary is re-checked from old.
      if(checkbox.is(':checked')){
         handleCheckInput(checkbox, thisField, dependencyJson);
      }
      // register the change event to handle subsequent checkbox state changes.
      checkbox.change(function(){
        if(checkbox.is(':checked')){
          handleCheckInput(checkbox, thisField, dependencyJson);
        }else{
          let idCurrent = checkbox.data('id');
          //remove hidden field with this value.
          thisField.find('input.primary_hidden[value="'+idCurrent+'"]').remove();

          // uncheck and active secondary checkboxs if are not in other selected primary.
          var secondary = dependencyJson[idCurrent];

          var selected = [];
          thisField.find('input.primary_hidden').each(function (index, input){
            selected.push( $(this).val() );
          });

          $.each(secondary, function(index, secondaryItem){
            var ok = 1;

            $.each(selected, function(index2, selectedItem){
              if( dependencyJson[selectedItem].indexOf(secondaryItem) != -1 ){
                ok =0;
              }
            });

            if(ok){
              thisField.find('input.secondary_list[value="'+secondaryItem+'"]').prop('checked', false);
              thisField.find('input.secondary_list[value="'+secondaryItem+'"]').prop('disabled', false);
              thisField.find('input.secondary_list[value="'+secondaryItem+'"]').removeAttr('forced-select');
            }
          });

        }
        });
    });


    thisField.find('.secondary_list').click(function(){

      var idCurrent = $(this).data('id');
      if($(this).is(':checked')){
        //add hidden field with this value
        var nameInput = thisField.find('.hidden_fields_secondary').data('name');
        var inputToAdd = $('<input type="hidden" class="secondary_hidden" name="'+nameInput+'[]" value="'+idCurrent+'">');

        thisField.find('.hidden_fields_secondary').append(inputToAdd);

      }else{
        //remove hidden field with this value
        thisField.find('input.secondary_hidden[value="'+idCurrent+'"]').remove();
      }
    });

}

  