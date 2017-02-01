YUI.add('moodle-enrol_ilios-groupchoosers', function(Y) {
    var GROUPCHOOSERS = function() {
        GROUPCHOOSERS.superclass.constructor.apply(this, arguments);
    };

  // GROUPCHOOSERS.prototype = {
  //      getnextoptions : function(thisselect, nextselect, btn) {
  //       // clear next select first
  //       nextselect.set('value', '');
  //       btn.simulate('click');
  //     }
  // };

    Y.extend(GROUPCHOOSERS, Y.Base, {
      initializer : function(params) {
            if (params && params.formid && params.courseid) {
              var selectbuttons = { "selectschool"      : "updateschool",
                                    "selectprogram"     : "updateprogram",
                                    "selectcohort"      : "updatecohort",
                                    "selectlearnergroup": "updatelearnergroup",
                                    "selectsubgroup"    : "updatesubgroup" };
              var selectnexts = { "selectschool"      : "selectprogram",
                                  "selectprogram"     : "selectcohort",
                                  "selectcohort"      : "selectlearnergroup",
                                  "selectlearnergroup": "selectsubgroup",
                                  "selectsubgroup"    : "selectinstructorgroup" };

              for (var sel in selectnexts) {
                var thisselect = Y.one('#'+params.formid+' #id_' + sel);
                var updatebutton = Y.one('#'+params.formid+' #id_'+ selectbuttons[sel] + 'options');

                updatebutton.setStyle('display', 'none');

                thisselect.on('change', function(e) {
                  var elementname = e.currentTarget.get('name');
                  var elementvalue = e.currentTarget.get('value');
                  var selectnexts = { "selectschool"      : "selectprogram",
                                      "selectprogram"     : "selectcohort",
                                      "selectcohort"      : "selectlearnergroup",
                                      "selectlearnergroup": "selectsubgroup" };

                  var hasid = elementvalue.indexOf(':');
                  var nextselect = Y.one('#'+params.formid+' #id_' + selectnexts[elementname]);

                  while (nextselect) {
                    nextselect.set('value', '');
                    nextselect.all('option').slice(1).remove();
                    nextselect.set('disabled', 'disabled');
                    nextselect = Y.one('#'+params.formid+' #id_' + selectnexts[nextselect.get('name')]);
                  }

                  var usertype = Y.one('#'+params.formid+' #id_selectusertype').get('value');
                  if (hasid > 0) {
                    var filterid = elementvalue.split(':')[0];
                    var uri = "/enrol/ilios/ajax.php?id="+params.courseid+"&action=get"+selectnexts[elementname]+'options&filterid='+filterid+'&sesskey='+M.cfg.sesskey+'&usertype='+usertype;

                    YUI().use(['base','node','json-parse','io-base'], function (Y) {

                      Y.on('io:complete',
                           function (id, o, args) {

                             var selectel = Y.one('#'+args[0]+' #id_' + args[1]);
                             try {
                               var response = Y.JSON.parse(o.responseText);
                               if (response.error) {
                                 new M.core.ajaxException(response);
                               } else {
                                 var options = response.response;
                                 for (var key in options) {
                                   selectel.append('<option value="'+key+'">'+options[key]+'</option>');
                                 }
                                 selectel.removeAttribute('disabled');
                               }
                             } catch (e) {
                               return new M.core.exception(e);
                             }
                             return true;
                           },Y,[ params.formid, selectnexts[elementname] ]);

                      var request = Y.io(M.cfg.wwwroot+uri);

                    });

                  // Old way, not so ajaxy
                  //var updatebutton = Y.one('#'+params.formid+' #id_'+ selectbuttons[elementname] + 'options');
                  //updatebutton.simulate('click');
                  }
                });
              }
            }
      }
    });

    M.enrol_ilios = M.enrol || {};
    M.enrol_ilios.init_groupchoosers = function(params) {
        return new GROUPCHOOSERS(params);
    }
}, '@VERSION@', {requires:['base', 'node', 'node-event-simulate']});
