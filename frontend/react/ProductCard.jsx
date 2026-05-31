function ProductCard({ product }) {
  const typeLabels = {
    achat_immediat: 'Achat immédiat',
    enchere: 'Enchère',
    negociation: 'Négociation',
    mixte: 'Mixte'
  };
  const canBuy = product.type_vente === 'achat_immediat' || product.type_vente === 'mixte';
  const canBid = (product.type_vente === 'enchere' || product.type_vente === 'mixte') && product.has_auction;
  const canNegotiate = product.type_vente === 'negociation' || product.type_vente === 'mixte';
  return (
    <article className="product-card">
      <div className="product-media">
        <a href={product.details_url}><img loading="lazy" src={product.image_url} alt={product.titre} /></a>
        <span className={`product-badge badge-${product.type_vente}`}>{typeLabels[product.type_vente] || product.type_vente}</span>
      </div>
      <div className="product-body">
        <p className="muted">{product.nom_categorie} · {product.etat_produit || 'sélection vérifiée'}</p>
        <h3><a href={product.details_url}>{product.titre}</a></h3>
        <p className="price">{product.display_price}</p>
        <div className="card-actions">
          <a className="btn secondary" href={product.details_url}>Voir</a>
          {canBuy && <a className="btn" href={product.details_url}>Acheter</a>}
          {canBid && <a className="btn" href={product.auction_url}>Enchérir</a>}
          {canNegotiate && <a className="btn secondary" href={product.negotiation_url}>Négocier</a>}
        </div>
      </div>
    </article>
  );
}
