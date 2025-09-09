(function() {
  const { registerPaymentMethod } = window.wc?.wcBlocksRegistry || {};
  if (!registerPaymentMethod) {
    return;
  }

  const settings = window.wc?.wcSettings?.getSetting('visiofex_data', {}) || {};

  const Label = () => {
    const el = window.wp.element;
    const children = [];
    
    // Title only (logo removed intentionally)
    children.push(el.createElement('span', {
      style: { verticalAlign: 'middle', marginRight: '8px', fontWeight: 500 }
    }, settings.title || 'Pay by Credit or Debit Card'));
    
  // Card brand icons container
  const cardIcons = [];
    
    // Visa icon
    cardIcons.push(el.createElement('img', {
      key: 'visa',
      src: settings.pluginUrl + '/assets/images/visa.svg',
      alt: 'Visa',
      className: 'visiofex-card-icon',
      style: { marginLeft: '8px', marginRight: '2px', verticalAlign: 'middle' }
    }));
    
    // Mastercard icon  
    cardIcons.push(el.createElement('img', {
      key: 'mastercard',
      src: settings.pluginUrl + '/assets/images/mastercard.svg',
      alt: 'Mastercard',
      className: 'visiofex-card-icon',
      style: { marginRight: '2px', verticalAlign: 'middle' }
    }));
    
    // Amex icon
    cardIcons.push(el.createElement('img', {
      key: 'amex',
      src: settings.pluginUrl + '/assets/images/amex.svg',
      alt: 'American Express',
      className: 'visiofex-card-icon',
      style: { marginRight: '2px', verticalAlign: 'middle' }
    }));
    
    // Discover icon
    cardIcons.push(el.createElement('img', {
      key: 'discover',
      src: settings.pluginUrl + '/assets/images/discover.svg',
      alt: 'Discover',
      className: 'visiofex-card-icon',
      style: { verticalAlign: 'middle' }
    }));
    
    children.push(el.createElement('span', {
      className: 'visiofex-card-icons',
      style: { marginLeft: '8px', verticalAlign: 'middle' }
    }, ...cardIcons));
    
    return el.createElement('div', { 
      style: { display: 'flex', alignItems: 'center' }
    }, ...children);
  };

  const Content = () => {
    const el = window.wp.element;
    const raw = (settings.description || '').toString();

    // Strip any HTML tags the merchant may have entered, we control formatting ourselves
    const desc = raw.replace(/<[^>]*>/g, '');
    if (!desc) return null;
    
    const [lead, ...restParts] = desc.trim().split(/\r?\n+/);
    const leadEl = el.createElement('strong', null, lead || '');
    const restText = restParts.join(' ').trim();
    const restEl = restText ? el.createElement('span', { style: { display: 'block', marginTop: '8px' } }, restText) : null;
    
    return el.createElement('div', null, leadEl, restEl);
  };

  registerPaymentMethod({
    name: 'visiofex',
    label: window.wp.element.createElement(Label),
    content: window.wp.element.createElement(Content),
    edit: window.wp.element.createElement(Content),
    ariaLabel: settings.title || 'VisioFex Pay',
    canMakePayment: () => true,
    supports: { features: ['products'] },
  });
})();
