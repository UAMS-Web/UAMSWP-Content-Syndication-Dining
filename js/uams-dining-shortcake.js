function updateDiningOptionsListener(changed, collection, shortcode) {

    function attributeByName(name) {
        return _.find(
            collection,
            function (viewModel) {
                return name === viewModel.model.get('attr');
            }
        );
    }

    var updatedVal = changed.value,
        category = attributeByName('cat'),
        title = attributeByName('title');

    if( typeof updatedVal === 'undefined' ) {
        return;
    }

    if ('list' === updatedVal)  {
        category.$el.show();
        title.$el.show();
    } else if ('full' === updatedVal)  {
        category.$el.hide();
        title.$el.hide();
    } else if ('list' !== updatedVal)  {
        category.$el.hide();
        title.$el.hide();
    } else {
        category.$el.hide();
        title.$el.hide();
    }
}
wp.shortcake.hooks.addAction('uamswp_dining.output', updateDiningOptionsListener );