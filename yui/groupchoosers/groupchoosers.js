YUI.add('moodle-enrol_ilios-groupchoosers', function(Y) {
    var GROUPCHOOSERS = function() {
        GROUPCHOOSERS.superclass.constructor.apply(this, arguments);
    }

  GROUPCHOOSERS.prototype = {
      getnextoptions : function(thisselect, nextselect, btn) {
        // clear next select first
        nextselect.set('value', '');
        btn.simulate('click');
      }
  };

    Y.extend(GROUPCHOOSERS, Y.Base, {
      initializer : function(params) {
            if (params && params.formid && params.courseid) {
              var selectbuttons = { "selectschool": "updateschool",
                                    "selectprogram": "updateprogram",
                                    "selectcohort": "updatecohort",
                                    "selectlearnergroup": "updatelearnergroup" };
              var selectnexts = { "selectschool": "selectprogram",
                                    "selectprogram": "selectcohort",
                                    "selectcohort": "selectlearnergroup",
                                    "selectlearnergroup": "selectsubgroup" };
              for (var sel in selectnexts) {
                var thisselect = Y.one('#'+params.formid+' #id_' + sel);
                var updatebutton = Y.one('#'+params.formid+' #id_'+ selectbuttons[sel] + 'options');

                updatebutton.setStyle('display', 'none');
                thisselect.on('change', function(e) {
                  var elementname = e.currentTarget.get('name');
                  var elementvalue = e.currentTarget.get('value');
                  var hasid = elementvalue.indexOf(':');
                  var nextselect = Y.one('#'+params.formid+' #id_' + selectnexts[elementname]);

                  if (hasid > 0) {
                    var filterid = elementvalue.split(':')[0];
                    // @TODO: Fix this url here.
                    var uri = "/enrol/ilios/ajax.php?id="+params.courseid+"&action=get"+selectnexts[elementname]+'options&filterid='+filterid+'&sesskey='+M.cfg.sesskey;

                    Y.on('io:complete', function (id, o, args) {
                      var selectel = Y.one('#'+args[0]+' #id_' + args[1]);
                      try {
                        var response = Y.JSON.parse(o.responseText);
                        if (response.error) {
                          new M.core.ajaxException(options);
                        } else {
                          var testoptions = { "": "Choose ...", "1:Freaking":"Freaking", "2:Not":"Not", "3:Working":"Working"};
                          var options = response.response;
                          for (var key in options) {
                            selectel.append('<option value="'+key+'">'+options[key]+'</option>');
                          }
                        }
                      } catch (e) {
                        return new M.core.exception(e);
                      }
                    }, Y, [ params.formid, selectnexts[elementname] ]);

                    var request = Y.io(M.cfg.wwwroot+uri);
                  }

                  while (nextselect) {
                    nextselect.set('value', '');
                    nextselect = Y.one('#'+params.formid+' #id_' + selectnexts[nextselect.get('name')]);
                  }

                  // Old way, not so ajaxy
                  //var updatebutton = Y.one('#'+params.formid+' #id_'+ selectbuttons[elementname] + 'options');
                  //updatebutton.simulate('click');
                });
              }

              // for (var sel in selectbuttons) {
              //   var updatebutton = Y.one('#'+params.formid+' #id_'+ selectbuttons[sel] + 'options');
              //   var selectelement = Y.one('#'+params.formid+' #id_' + sel);

              //   // selectelement.on('change', function(e) {
              //   //   // get the school id
              //   //   this.getprograms( schoolid );
              //   // }
              //   if (updatebutton && selectelement) {
              //       updatebutton.setStyle('display', 'none');
              //       selectelement.on('change', function(e) {
              //         var elementname = e.currentTarget.get('name');
              //         switch(elementname) {
              //           case 'selectschool':
              //                var select2 = Y.one('#'+params.formid+' #id_' + 'selectprogram');
              //                select2.set('value', '');
              //           case 'selectprogram':
              //                var select3 = Y.one('#'+params.formid+' #id_' + 'selectcohort');
              //                select3.set('value', '');
              //           case 'selectcohort':
              //                var select4 = Y.one('#'+params.formid+' #id_' + 'selectlearnergroup');
              //                select4.set('value', '');
              //           default:
              //                updatebutton.simulate('click');
              //         }
              //       });
              //   }
              // }
            }
      }
    });

    M.enrol_ilios = M.enrol || {};
    M.enrol_ilios.init_groupchoosers = function(params) {
        return new GROUPCHOOSERS(params);
    }
}, '@VERSION@', {requires:['base', 'node', 'node-event-simulate', 'io-base']});
