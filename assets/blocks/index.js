(function() {
  const { registerPaymentMethod } = window.wc?.wcBlocksRegistry || {};
  if (!registerPaymentMethod) {
    return;
  }

  const settings = window.wc?.wcSettings?.getSetting('visiofex_data', {}) || {};

  const Label = () => {
    const el = window.wp.element;
    const children = [];
    if (settings.icon) {
      children.push(el.createElement('img', {
        src: settings.icon,
        alt: settings.title || 'VisioFex',
        style: { height: '18px', verticalAlign: 'middle', marginRight: '6px' }
      }));
    }
    children.push(settings.title || 'VisioFex Pay');
    return el.createElement('span', null, ...children);
  };

  const Content = () =>
    settings.description ? window.wp.element.createElement('div', null, settings.description) : null;

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
