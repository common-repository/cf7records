//
// Updates "Select all" control in a data table
//
function updateDataTableSelectAllCtrl(table){
    jQuery("#deletebtn").remove();
    jQuery(".dataTables_info").prepend('<button id="deletebtn" class="button button-primary button-small">Delete</button> ');
    var $table             = table.table().node();
    var $chkbox_all        = jQuery('tbody input[type="checkbox"]', $table);
    var $chkbox_checked    = jQuery('tbody input[type="checkbox"]:checked', $table);
    var chkbox_select_all  = jQuery('thead input[name="select_all"]', $table).get(0);

    // If none of the checkboxes are checked
    if($chkbox_checked.length === 0){
        chkbox_select_all.checked = false;
        if('indeterminate' in chkbox_select_all){
            chkbox_select_all.indeterminate = false;
        }

        // If all of the checkboxes are checked
    } else if ($chkbox_checked.length === $chkbox_all.length){
        chkbox_select_all.checked = true;
        if('indeterminate' in chkbox_select_all){
            chkbox_select_all.indeterminate = false;
        }

        // If some of the checkboxes are checked
    } else {
        chkbox_select_all.checked = true;
        if('indeterminate' in chkbox_select_all){
            chkbox_select_all.indeterminate = true;
        }
    }
}

jQuery(document).ready(function($){

    /*$('.export_to_csv').click(function (e) {
        $.ajax({
            type: "POST",
            url : MyAjax.ajaxurl,
            dataType: "json",
            data: {
                action: 'nexus_cf7export_options',
                data: '',
            },
            success: function (result) {
            },
        });
    });*/

    // Array holding selected row IDs
    var rows_selected = [];
    var table = jQuery('#example').DataTable({
        'columnDefs': [{
            'targets': 0,
            'searchable':false,
            'orderable':false,
            'className': 'dt-body-center',
            'render': function (data, type, full, meta){
                return '<input type="checkbox">';
            }
        }],
        'order': [1, 'desc'],
        'rowCallback': function(row, data, dataIndex){
            // Get row ID
            var rowId = data[0];

            // If row ID is in the list of selected row IDs
            if(jQuery.inArray(rowId, rows_selected) !== -1){
                jQuery(row).find('input[type="checkbox"]').prop('checked', true);
                jQuery(row).addClass('selected');
            }
        }
    });

    // Handle click on checkbox
    jQuery('#example tbody').on('click', 'input[type="checkbox"]', function(e){
        var $row = jQuery(this).closest('tr');

        // Get row data
        var data = table.row($row).data();

        // Get row ID
        var rowId = data[0];

        // Determine whether row ID is in the list of selected row IDs
        var index = jQuery.inArray(rowId, rows_selected);

        // If checkbox is checked and row ID is not in list of selected row IDs
        if(this.checked && index === -1){
            rows_selected.push(rowId);

            // Otherwise, if checkbox is not checked and row ID is in list of selected row IDs
        } else if (!this.checked && index !== -1){
            rows_selected.splice(index, 1);
        }

        if(this.checked){
            $row.addClass('selected');
        } else {
            $row.removeClass('selected');
        }

        // Update state of "Select all" control
        updateDataTableSelectAllCtrl(table);

        // Prevent click event from propagating to parent
        e.stopPropagation();
    });

    // Handle click on table cells with checkboxes
    jQuery('#example').on('click', 'tbody td, thead th:first-child', function(e){
        jQuery(this).parent().find('input[type="checkbox"]').trigger('click');
    });

    // Handle click on "Select all" control
    jQuery('thead input[name="select_all"]', table.table().container()).on('click', function(e){
        if(this.checked){
            jQuery('tbody input[type="checkbox"]:not(:checked)', table.table().container()).trigger('click');
        } else {
            jQuery('tbody input[type="checkbox"]:checked', table.table().container()).trigger('click');
        }

        // Prevent click event from propagating to parent
        e.stopPropagation();
    });

    // Handle table draw event
    table.on('draw', function(){
        // Update state of "Select all" control
        updateDataTableSelectAllCtrl(table);
    });

    // Handle form submission event
    jQuery('#frm-example').on('submit', function(e){
        var form = this;

        // Iterate over all selected checkboxes
        jQuery.each(rows_selected, function(index, rowId){
            // Create a hidden element

            $.ajax({
                type: "POST",
                url : MyAjax.ajaxurl,
                dataType: "json",
                data: {
                    action: 'nexus_cf7_options',
                    data: rowId,
                },
                success: function (result) {
                    //jQuery("#delete_"+rowId).fadeOut("slow");
                    location.reload();
                },
            });


            jQuery(form).append(
                jQuery('<input>')
                    .attr('type', 'hidden')
                    .attr('name', 'id[]')
                    .val(rowId)


            );
        });

        // FOR DEMONSTRATION ONLY

        // Output form data to a console
        jQuery('#example-console').text($(form).serialize());
        console.log("Form submission", $(form).serialize());

        // Remove added elements
        jQuery('input[name="id\[\]"]', form).remove();

        // Prevent actual form submission
        e.preventDefault();
    });
    jQuery(".dataTables_info").prepend('<button id="deletebtn" class="button button-primary button-small">Delete</button> ');

});


