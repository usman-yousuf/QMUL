var loqateObj = (function () {
	'use strict';
	let fields = [
		{ element: "address", field: "", mode: pca.fieldMode.SEARCH },
		{ element: "address", field: "Line1", mode: pca.fieldMode.POPULATE },
		{ element: "town", field: "City", mode: pca.fieldMode.POPULATE },
		{ element: "county", field: "Province", mode: pca.fieldMode.POPULATE },
		{ element: "postcode", field: "PostalCode" },
		{ element: "country", field: "CountryName", mode: pca.fieldMode.COUNTRY }
	];
	let options = { key: "ZR19-GH71-WN99-BK15", search: { countries: "GB" }, setCountryByIP: true, reverseGeocode: {enabled: true, radius: 50, maxItems: 10}};
	return {
		setFields : function (inFlds) { fields = inFlds; },
		setOptions : function (inOpts) { options = inOpts; },
		init : function () { return new pca.Address(fields, options); }
	}
})();
var alumcontact_lookup = loqateObj.init();
