$("#get_sql").click(function() {
    var a_rules = false,
        use_prepared_statements, pst_placeholder;

    a_rules = $("#demo_rules1").jui_filter_rules("getRules", 0, []);

    if(!a_rules) {
        show_modal($("#modal_dialog"), $("#modal_dialog_content"), "Rules error...");
        return false;
    }

    if(a_rules.length == 0) {
        show_modal($("#modal_dialog"), $("#modal_dialog_content"), "No rules defined...");
        return false;
    }

    switch ($(this).index()) {
        case 0:
            use_prepared_statements = "no";
            pst_placeholder = null;
            break;
        case 1:
            use_prepared_statements = "yes";
            pst_placeholder = 'question_mark';
            break;
        case 2:
            use_prepared_statements = "yes";
            pst_placeholder = 'numbered';
            break;
    }

    $.ajax({
        type: 'POST',
        url: "ajax/ajax_get_sql.php",
        data: {
            a_rules: a_rules,
            use_ps: use_prepared_statements,
            pst_placeholder: pst_placeholder
        },
        dataType: "JSON",
        success: function(data) {
            var html = '';

            if(data.hasOwnProperty("error")) {
                $("#demo_rules1")
                    .jui_filter_rules("markRuleAsError", data["error"]["element_rule_id"], true)
                    .triggerHandler("onValidationError",
                    {
                        err_code: "filter_error_server_side",
                        err_description: "Server error during filter converion..." + '\n\n' + data["error"]["error_message"]
                    }
                );
            } else {
                html += '<pre id="demo_code"><ul>';
                html += '<li>SQL: \n\n' + data["sql"] + '\n\n</li>';
                html += '<li>Bind params: \n\n' + JSON.stringify(data["bind_params"], null, '    ') + '</li>';
                html += '</ul></pre>';
                show_modal($("#modal_dialog"), $("#modal_dialog_content"), html);
            }

        }
    });

});
