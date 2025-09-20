
/*! Minimal jQuery Checkbox Parent Controller v0.1 */
(function($){
  $.fn.checkbox = function(callback){
    var $parent = this;
    var childSelector = $parent.data('children') || '';
    var $children = $(childSelector);

    function setChildren(checked){
      $children.each(function(){
        var prev = this.checked;
        this.checked = checked;
        // trigger change only if state actually changed
        if (prev !== checked) $(this).trigger('change');
      });
      emit();
    }
    function emit(){
      var vals = $children.filter(':checked').map(function(){ return $(this).val(); }).get();
      if (typeof callback === 'function') callback(vals);
      $parent.trigger('checkbox:change', [vals]);
    }

    // initial behaviour
    $parent.on('change', function(){
      setChildren(this.checked);
    });

    // children mutations should update parent indeterminate state
    $children.on('change', function(){
      var total = $children.length;
      var act   = $children.filter(':checked').length;
      $parent.prop('indeterminate', act>0 && act<total);
      $parent.prop('checked', act===total);
      emit();
    });

    // initial sync at load
    setTimeout(function(){
      var allChecked = $children.length && $children.filter(':checked').length === $children.length;
      $parent.prop('checked', allChecked);
      $parent.prop('indeterminate', !allChecked && $children.filter(':checked').length>0);
    },0);

    return this;
  };
})(jQuery);
