(function (angular, $, _) {

  angular.module('crmKasUser').config(function ($routeProvider) {
    $routeProvider.when('/minside', {
      controller: 'CrmKasUserMinSide',
      controllerAs: '$ctrl',
      templateUrl: '~/crmKasUser/MinSide.html',

      // If you need to look up data when opening the page, list it out
      // under "resolve".
      resolve: {
        myContact: function (crmApi) {
          return crmApi('Contact', 'getsingle', {
            id: 'user_contact_id'
//              return: ['first_name', 'last_name', 'external_identifier']
          });
        }
      }
    });
  }
  );

  // The controller uses *injection*. This default injects a few things:
  //   $scope -- This is the set of variables shared between JS and HTML.
  //   crmApi, crmStatus, crmUiHelp -- These are services provided by civicrm-core.
  //   myContact -- The current contact, defined above in config().
  angular.module('crmKasUser')
    .controller('CrmKasUserMinSide', function($scope, crmApi4, crmStatus, crmUiHelp, myContact) {
  // The ts() and hs() functions help load strings for this module.
  var ts = $scope.ts = CRM.ts('kas_user');
    var hs = $scope.hs = crmUiHelp({file: 'CRM/crmKasUser/MinSide'}); // See: templates/CRM/crmKasUser/MinSide.hlp
    // Local variable for this controller (needed when inside a callback fn where `this` is not available).
    var ctrl = this;
    // We have myContact available in JS. We also want to reference it in HTML.
    this.myContact = myContact;
    var inputEl = angular.element(document.querySelector('#addressInput'));
    $scope.hidePrimaryTab = false;
    $scope.hideEventTeamTab = true;
    $scope.contactId = null;
    $scope.contact = null;
    $scope.samboId = null;
    $scope.samboName = null;
    $scope.sambo = null;
    $scope.primarySambo = false;
    $scope.boatId = null;
    $scope.boatName = null;
    $scope.boat = null;
    $scope.boatFieldLabels = null;
    $scope.extIdInput = null;
    $scope.extIdSuggest = [];
    $scope.boatOwnerId = null;
    $scope.boatInput = null;
    $scope.boatSuggest = [];
    $scope.addressInput = null;
    $scope.addressSuggest = [];
    $scope.addressResponse = [];
    $scope.masterEventId = null;
    $scope.colSelect = [];
    $scope.columnOptions = [];
    $scope.masterParticipantsHeaders = [];
    $scope.masterParticipants = null;
    $scope.masterParticipantsChecked = new Map();
    function hideTabs() {
    $scope.hidePrimaryTab = true;
      $scope.hideEventTeamTab = true;
    }

  $scope.selectTab = function (tab) {
  hideTabs();
    if (tab == 'primary') {
  $scope.hidePrimaryTab = false;
  } else if (tab == 'event_teams') {
  $scope.hideEventTeamTab = false;
  }
  };
    $scope.clearSearch = function () {
    $scope.contact = null;
      $scope.contactId = null;
      $scope.samboId = null;
      $scope.samboName = null;
      $scope.primarySambo = false;
      $scope.boatId = null;
      $scope.boatName = null;
    };
    $scope.switchTo = function (id) {
    $scope.contactId = id;
      $scope.contactSelected();
    };
    $scope.contactSelected = function () {
    crmApi4('Contact', 'get', {
    select: ["external_identifier", "display_name", "address_primary.*", "email_primary.email", "phone_primary.phone"],
      where: [["id", "=", $scope.contactId]]
    }).then(function (contacts) {
    $scope.contact = contacts[0];
      $scope.extIdInput = null;
      $scope.extIdSuggest = [];
      $scope.boatId = null;
      $scope.samboId = null;
      crmApi4('Relationship', 'get', {
      select: ["relationship_type_id:name", "contact_id_a", "contact_id_a.display_name", "contact_id_b", "contact_id_b.display_name"],
        where: [["OR", [["contact_id_b", "=", $scope.contactId],
        ["contact_id_a", "=", $scope.contactId]]]]
      }).then(function (relationships) {
    relationships.forEach((rel) => {
    if (rel['relationship_type_id:name'] === 'Bådejer af') {
    $scope.boatId = rel.contact_id_b;
      $scope.boatName = rel['contact_id_b.display_name'];
      $scope.getBoatData();
    } else if (rel['relationship_type_id:name'].search('Sambo') >= 0) {
    if (rel.contact_id_b == $scope.contactId) {
    $scope.samboId = rel.contact_id_a;
      $scope.samboName = rel['contact_id_a.display_name'];
      $scope.primarySambo = false;
    } else {
    $scope.samboId = rel.contact_id_b;
      $scope.samboName = rel['contact_id_b.display_name'];
      $scope.sambo1 = true;
    }
    }
    });
    }, function (failure) {
    console.log(failure);
    });
    }, function (failure) {
    console.log(failure);
    });
    };
    $scope.getBoatData = function () {
    if ($scope.boatFieldLabels === null) {
    crmApi4('Contact', 'getFields', {
    where: [["name", "LIKE", "B_D%"]],
      select: ["title"]
    }, "name").then(function (fields) {
    $scope.boatFieldLabels = fields;
    }, function (failure) {
    console.log(failure);
    });
    }
    crmApi4('Contact', 'get', {
    select: ["B_d_data.B_dtype", "B_d_data.L_ngde",
      "B_d_data.Bredde", "B_d_data.Dybgang", "B_d_data.V_gt",
      "B_d_data.Forsikringsselskab", "B_d_data.Policenummer",
      "B_d_data.Sejl_Nummer", "B_d_data.Kaldesignal", "B_d_data.Mmsi"],
      where: [["id", "=", $scope.boatId]]
    }).then(function (contacts) {
    $scope.boat = contacts[0];
      delete($scope.boat.id); // remove id field
    }, function (failure) {
    console.log(failure);
    });
    };
    $scope.extIdInputChange = function () {
    let foundContact = $scope.extIdSuggest.find((c) => c.external_identifier === $scope.extIdInput);
      if (foundContact) {
    $scope.contactId = foundContact.id;
      $scope.extId = foundContact.external_identifier;
      $scope.contactSelected();
      $scope.extIdSuggest = [];
      return;
    }
    if ($scope.extIdInput.length >= 2) {
    crmApi4('Contact', 'get', {
    select: ["id", "external_identifier", "display_name"],
      join: [["Membership AS membership", "LEFT", ["membership.contact_id", "=", "id"], ["membership.end_date", "IS NULL"]]],
      where: [["external_identifier", "LIKE", $scope.extIdInput + '%'], ["membership.id", "IS NOT NULL"]],
      limit: 25
    }).then(function (contacts) {
    $scope.extIdSuggest = contacts;
    }, function (failure) {
    console.log(failure);
    });
    } else {
    $scope.contactId = null;
      $scope.extId = null;
      $scope.extIdSuggest = [];
    }
    };
    $scope.boatInputChange = function () {
    let split = $scope.boatInput.split('|');
      if (split.length > 1) {
    $scope.contactId = split[1];
      $scope.contactSelected();
      $scope.boatSuggest = [];
      $scope.boatInput = null;
    } else if ($scope.boatInput.length >= 2) {
    crmApi4('Contact', 'get', {
    select: ["display_name", "contact.display_name", "contact.id"],
      join: [["Contact AS contact", "LEFT", "RelationshipCache"]],
      where: [["contact_type", "=", "Organization"],
      ["contact_sub_type", "=", "Boat"],
      ["organization_name", "LIKE", $scope.boatInput + "%"]],
      limit: 25
    }).then(function (contacts) {
    $scope.boatSuggest = contacts;
    }, function (failure) {
    console.log(failure);
    });
    } else {
    $scope.contactId = null;
      $scope.boat = null;
      $scope.boatSuggest = [];
    }
    };
    $scope.memberSelected = function () {
    console.log('member');
    };
    $scope.addressInputChange = function () {
//        if ($scope.addressResponse.length = 1) {
//          var selected = $scope.addressResponse.find((a) => a.forslagstekst = $scope.addressInput);
//          if (selected && selected.type == 'adresse') {
//            $http.get(selected.data.href)
//              .then(function successCallback(response) {
//                console.log(response.data);
//              }, function errorCallback(response) {
//                console.log(response);
//              });
//          }
//        }
    var query = 'q=' + $scope.addressInput + '&type=adresse&caretpos=' + $scope.addressInput.length;
      query += '&supplerendebynavn=true&stormodtagerpostnumre=true&multilinje=true&fuzzy=';
      $http.get('https://api.dataforsyningen.dk/autocomplete?' +
        query)
      .then(function successCallback(response) {
      if (response.data.length == 1) {
      $http.get(response.data[0].data.href)
        .then(function successCallback(response) {
        console.log(response.data);
        }, function errorCallback(response) {
        console.log(response);
        });
        return;
      }
      $scope.addressResponse = response.data;
        $scope.addressSuggest = response.data.map((a) => {
        let index = a.forslagstekst.indexOf("\n");
          if (index > 0) {
        return {
        value: a.forslagstekst.substring(0, index),
          forslag: a.forslagstekst
        };
        } else {
        return {
        value: a.forslagstekst,
          forslag: a.forslagstekst
        };
        }
        });
      }, function errorCallback(response) {
      console.log(response);
      });
    };
  });
})(angular, CRM.$, CRM._);
