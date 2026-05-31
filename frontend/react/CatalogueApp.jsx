function CatalogueApp() {
  const [products, setProducts] = React.useState([]);
  const [loading, setLoading] = React.useState(true);
  const [error, setError] = React.useState('');

  React.useEffect(() => {
    const params = new URLSearchParams(window.MN_INITIAL_FILTERS || {});
    fetch(`${window.MN_BASE_URL}backend/api/products/list.php?${params.toString()}`, { headers: { 'Accept': 'application/json' }})
      .then((r) => r.json())
      .then((data) => {
        if (!data.success) throw new Error(data.message || 'Erreur catalogue');
        setProducts(data.products || []);
        const serverCatalogue = document.getElementById('server-catalogue');
        if (serverCatalogue) serverCatalogue.style.display = 'none';
      })
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <div className="empty-state">Chargement dynamique du catalogue React…</div>;
  if (error) return <div className="react-error">Version serveur affichée. React : {error}</div>;
  if (!products.length) return <div className="empty-state">Aucun produit ne correspond aux filtres.</div>;
  return <div className="product-grid">{products.map((p) => <ProductCard key={p.id_product} product={p} />)}</div>;
}

ReactDOM.createRoot(document.getElementById('react-catalogue')).render(<CatalogueApp />);
