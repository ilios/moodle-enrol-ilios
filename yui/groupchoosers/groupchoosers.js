YUI.add('moodle-enrol_ilios-groupchoosers', function(Y) {
    var GROUPCHOOSERS = function() {
        GROUPCHOOSERS.superclass.constructor.apply(this, arguments);
    }

    Y.extend(GROUPCHOOSERS, Y.Base, {
        initializer : function(params) {
            if (params && params.formid) {
              var selectbuttons = { "selectschool": "updateschool",
                                    "selectprogram": "updateprogram",
                                    "selectcohort": "updatecohort",
                                    "selectlearnergroup": "updatelearnergroup" };
              for (var sel in selectbuttons) {
                var updatebutton = Y.one('#'+params.formid+' #id_'+ selectbuttons[sel] + 'options');
                var selectelement = Y.one('#'+params.formid+' #id_' + sel);
                if (updatebutton && selectelement) {
                    updatebutton.setStyle('display', 'none');
                    selectelement.on('change', function(e) {
                      var elementname = e.currentTarget.get('name');
                      switch(elementname) {
                        case 'selectschool':
                             var select2 = Y.one('#'+params.formid+' #id_' + 'selectprogram');
                             select2.set('value', '');
                        case 'selectprogram':
                             var select3 = Y.one('#'+params.formid+' #id_' + 'selectcohort');
                             select3.set('value', '');
                        case 'selectcohort':
                             var select4 = Y.one('#'+params.formid+' #id_' + 'selectlearnergroup');
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
