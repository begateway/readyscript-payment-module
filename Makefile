all:
	if [[ -e begateway.zip ]]; then rm begateway.zip; fi
	zip -r begateway.zip begateway -x "*/test/*" -x "*/.git/*" -x "*/examples/*" -x "begateway/view/form/begateway_model_paymenttype_begateway_*.tpl"
