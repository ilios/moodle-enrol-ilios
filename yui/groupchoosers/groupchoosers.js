YUI.add('moodle-enrol_ilios-groupchoosers', function(Y) {
    var GROUPCHOOSERS = function() {
        GROUPCHOOSERS.superclass.constructor.apply(this, arguments);
    }

    Y.extend(GROUPCHOOSERS, Y.Base, {
        initializer : function(params) {
            if (params && params.formid) {
              var selectbuttons = { "selectschool": "updateschool",
                                    "customint2": "updateprogram",
                                    "customint3": "updateprogramyear",
                                    "customchar1": "updategroup" };
              for (var sel in selectbuttons) {
                var updatebutton = Y.one('#'+params.formid+' #id_'+ selectbuttons[sel] + 'options');
                var selectelement = Y.one('#'+params.formid+' #id_' + sel);
                if (updatebutton && selectelement) {
                    updatebutton.setStyle('display', 'none');
                    selectelement.on('change', function(e) {
                      var elementname = e.currentTarget.get('name');
                      switch(elementname) {
                        case 'selectschool':
                             var select2 = Y.one('#'+params.formid+' #id_' + 'customint2');
                             select2.set('value', '');
                        case 'customint2':
                             var select3 = Y.one('#'+params.formid+' #id_' + 'customint3');
                             select3.set('value', '');
                        case 'customint3':
                             var select4 = Y.one('#'+params.formid+' #id_' + 'customchar1');
                             select4.set('value', '');
                        default:
                            updatebutton.simulate('click');
                      }
                    });
                }
              }
            }
        }
    });

    M.enrol_ilios = M.enrol || {};
    M.enrol_ilios.init_groupchoosers = function(params) {
        return new GROUPCHOOSERS(params);
    }
}, '@VERSION@', {requires:['base', 'node', 'node-event-simulate']});
