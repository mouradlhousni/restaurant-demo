<?php
/**
 * Demo content.
 *
 * @package OliveEmber\Reservations
 */

namespace OliveEmber\Reservations\Cli;

defined( 'ABSPATH' ) || exit;

/**
 * Static demo data set: menu, guests and pages.
 */
final class SeedData {

	/**
	 * Menu sections: slug => [name, order, description].
	 *
	 * @return array<string, array{0: string, 1: int, 2: string}>
	 */
	public static function sections(): array {
		return array(
			'starters' => array( 'Starters', 10, 'To share, or not.' ),
			'mains'    => array( 'Mains', 20, 'From the wood fire and the sea.' ),
			'desserts' => array( 'Desserts', 30, 'A sweet ending.' ),
			'wines'    => array( 'Wines', 40, 'Natural and organic, mostly from Provence. By the glass.' ),
		);
	}

	/**
	 * Allergens (EU list): slug => name.
	 *
	 * @return array<string, string>
	 */
	public static function allergens(): array {
		return array(
			'gluten'      => 'Gluten',
			'crustaceans' => 'Crustaceans',
			'eggs'        => 'Eggs',
			'fish'        => 'Fish',
			'milk'        => 'Milk',
			'nuts'        => 'Tree nuts',
			'celery'      => 'Celery',
			'mustard'     => 'Mustard',
			'sesame'      => 'Sesame',
			'sulphites'   => 'Sulphites',
			'molluscs'    => 'Molluscs',
		);
	}

	/**
	 * Diets: slug => name.
	 *
	 * @return array<string, string>
	 */
	public static function diets(): array {
		return array(
			'vegetarian'  => 'Vegetarian',
			'vegan'       => 'Vegan',
			'gluten-free' => 'Gluten-free',
			'dairy-free'  => 'Dairy-free',
		);
	}

	/**
	 * Thirty dishes. `art` drives the generated illustration.
	 *
	 * @return array<int, array{name: string, description: string, price: float, section: string, allergens: array<int, string>, diets: array<int, string>, chefs_pick: bool, art: string}>
	 */
	public static function dishes(): array {
		$d = static fn( string $name, string $description, float $price, string $section, array $allergens, array $diets, string $art, bool $chefs_pick = false ): array => array(
			'name'        => $name,
			'description' => $description,
			'price'       => $price,
			'section'     => $section,
			'allergens'   => $allergens,
			'diets'       => $diets,
			'chefs_pick'  => $chefs_pick,
			'art'         => $art,
		);

		return array(
			// Starters.
			$d( 'Charred Flatbread & Whipped Feta', 'Wood-fired flatbread, whipped feta, Aleppo pepper honey, wild thyme.', 9, 'starters', array( 'gluten', 'milk' ), array( 'vegetarian' ), 'flatbread', true ),
			$d( 'Heirloom Tomato Salad', 'Late-summer tomatoes, peach, basil oil, smoked almonds, fleur de sel.', 12, 'starters', array( 'nuts' ), array( 'vegan', 'gluten-free', 'dairy-free' ), 'tomato' ),
			$d( 'Sea Bream Crudo', 'Line-caught bream, blood orange, fennel pollen, Ligurian olive oil.', 15, 'starters', array( 'fish' ), array( 'gluten-free', 'dairy-free' ), 'crudo' ),
			$d( 'Grilled Octopus', 'Ember-grilled octopus, smoked paprika, crushed potatoes, caper salsa verde.', 17, 'starters', array( 'molluscs', 'mustard' ), array( 'gluten-free', 'dairy-free' ), 'octopus' ),
			$d( 'Burrata & Roasted Grapes', 'Puglian burrata, wood-roasted grapes, saba, toasted hazelnuts.', 14, 'starters', array( 'milk', 'nuts' ), array( 'vegetarian', 'gluten-free' ), 'burrata' ),
			$d( 'Smoky Aubergine Dip', 'Fire-blistered aubergine, tahini, pomegranate, mint, warm pita.', 10, 'starters', array( 'sesame', 'gluten' ), array( 'vegan', 'dairy-free' ), 'dip' ),
			$d( 'Panisse Fries', 'Marseille chickpea fries, lemon aioli, piment d\'Espelette.', 8, 'starters', array( 'eggs', 'mustard' ), array( 'vegetarian', 'gluten-free' ), 'fries' ),
			$d( 'Fish Soup, Rouille & Croutons', 'Rockfish soup the Marseille way, saffron rouille, garlic croutons, gruyère.', 13, 'starters', array( 'fish', 'crustaceans', 'gluten', 'milk', 'eggs', 'celery' ), array(), 'soup' ),

			// Mains.
			$d( 'Whole Grilled Sea Bass', 'Sea bass cooked over vine embers, lemon, wild fennel, new potatoes.', 32, 'mains', array( 'fish' ), array( 'gluten-free', 'dairy-free' ), 'fish', true ),
			$d( 'Bouillabaisse for Two', 'Rockfish, gurnard, mussels and langoustines in saffron broth, rouille. Price per person.', 39, 'mains', array( 'fish', 'crustaceans', 'molluscs', 'celery', 'eggs' ), array( 'gluten-free', 'dairy-free' ), 'stew' ),
			$d( 'Lamb Shoulder, 12 Hours', 'Slow-roasted Sisteron lamb, chickpeas, preserved lemon, harissa yoghurt.', 29, 'mains', array( 'milk' ), array( 'gluten-free' ), 'lamb', true ),
			$d( 'Ember-Roasted Chicken', 'Free-range chicken, garlic confit, charred leeks, jus gras.', 24, 'mains', array( 'celery' ), array( 'gluten-free', 'dairy-free' ), 'chicken' ),
			$d( 'Dry-Aged Rib-Eye', '400 g rib-eye for one, aged 35 days, bone marrow butter, fries.', 38, 'mains', array( 'milk' ), array( 'gluten-free' ), 'steak' ),
			$d( 'Squid Ink Linguine', 'Hand-cut linguine, cuttlefish, cherry tomatoes, chilli, parsley.', 23, 'mains', array( 'gluten', 'eggs', 'molluscs' ), array( 'dairy-free' ), 'pasta' ),
			$d( 'Wild Mushroom Risotto', 'Carnaroli rice, cep and girolle mushrooms, aged parmesan, thyme.', 21, 'mains', array( 'milk', 'celery', 'sulphites' ), array( 'vegetarian', 'gluten-free' ), 'risotto' ),
			$d( 'Charred Cauliflower Steak', 'Whole-roasted cauliflower, romesco, golden raisins, pine nuts.', 19, 'mains', array( 'nuts' ), array( 'vegan', 'gluten-free', 'dairy-free' ), 'cauliflower' ),
			$d( 'Pissaladière', 'Provençal onion tart, anchovies, black olives, rocket salad.', 17, 'mains', array( 'gluten', 'fish' ), array( 'dairy-free' ), 'tart' ),
			$d( 'Grilled Prawns & Saffron Rice', 'Red prawns from the grill, saffron rice, garlic and parsley butter.', 28, 'mains', array( 'crustaceans', 'milk' ), array( 'gluten-free' ), 'prawns' ),

			// Desserts.
			$d( 'Olive Oil Cake', 'Moist lemon and olive oil cake, crème fraîche, candied citrus.', 9, 'desserts', array( 'gluten', 'eggs', 'milk' ), array( 'vegetarian' ), 'cake', true ),
			$d( 'Dark Chocolate Tart', '70% chocolate ganache, sea salt, olive oil, cocoa nib tuile.', 10, 'desserts', array( 'gluten', 'eggs', 'milk' ), array( 'vegetarian' ), 'chocolate' ),
			$d( 'Fig & Honey Pavlova', 'Crisp meringue, roasted figs, lavender honey, mascarpone cream.', 10, 'desserts', array( 'eggs', 'milk' ), array( 'vegetarian', 'gluten-free' ), 'pavlova' ),
			$d( 'Lemon Sorbet & Limoncello', 'Menton lemon sorbet, a shot of house limoncello.', 8, 'desserts', array(), array( 'vegan', 'gluten-free', 'dairy-free' ), 'sorbet' ),
			$d( 'Navettes & Coffee', 'Orange-blossom Marseille biscuits with an espresso.', 6, 'desserts', array( 'gluten', 'eggs', 'milk' ), array( 'vegetarian' ), 'biscuits' ),
			$d( 'Cheese from Provence', 'Three regional cheeses, fig jam, walnut bread.', 13, 'desserts', array( 'milk', 'gluten', 'nuts' ), array( 'vegetarian' ), 'cheese' ),

			// Wines.
			$d( 'Bandol Rosé, Domaine des Restanques', 'Mourvèdre-led rosé: peach, garrigue, saline finish. 12 cl.', 11, 'wines', array( 'sulphites' ), array( 'vegan', 'gluten-free', 'dairy-free' ), 'rose' ),
			$d( 'Cassis Blanc, Clos des Falaises', 'Marsanne and clairette from the cliffs of Cassis. 12 cl.', 10, 'wines', array( 'sulphites' ), array( 'vegan', 'gluten-free', 'dairy-free' ), 'white' ),
			$d( 'Palette Rouge, Château Belcodène', 'Old-vine grenache and mourvèdre, silky and spiced. 12 cl.', 14, 'wines', array( 'sulphites' ), array( 'vegan', 'gluten-free', 'dairy-free' ), 'red' ),
			$d( 'Côtes de Provence Orange', 'Skin-contact vermentino, apricot and bitter orange. 12 cl.', 9, 'wines', array( 'sulphites' ), array( 'vegan', 'gluten-free', 'dairy-free' ), 'orange' ),
			$d( 'Crémant de Die', 'Clairette bubbles, peach and honeysuckle. 12 cl.', 9, 'wines', array( 'sulphites' ), array( 'vegetarian', 'gluten-free', 'dairy-free' ), 'sparkling' ),
			$d( 'Muscat de Beaumes-de-Venise', 'Sweet fortified muscat with dessert. 8 cl.', 8, 'wines', array( 'sulphites' ), array( 'vegan', 'gluten-free', 'dairy-free' ), 'sweet' ),
		);
	}

	/**
	 * Guest first names.
	 *
	 * @return array<int, string>
	 */
	public static function first_names(): array {
		return array( 'Camille', 'Lucas', 'Léa', 'Hugo', 'Chloé', 'Louis', 'Manon', 'Gabriel', 'Inès', 'Jules', 'Sarah', 'Nathan', 'Emma', 'Adam', 'Zoé', 'Raphaël', 'Alice', 'Tom', 'Julia', 'Noah', 'Olivia', 'James', 'Sofia', 'Marco', 'Hannah', 'Yasmine', 'Karim', 'Elena', 'Pierre', 'Margaux' );
	}

	/**
	 * Guest last names.
	 *
	 * @return array<int, string>
	 */
	public static function last_names(): array {
		return array( 'Martin', 'Bernard', 'Dubois', 'Laurent', 'Moreau', 'Garcia', 'Rossi', 'Fontaine', 'Mercier', 'Blanc', 'Guerin', 'Bonnet', 'Smith', 'Müller', 'Ferrari', 'Benali', 'Costa', 'Lefèvre', 'Roux', 'Fabre', 'Chevalier', 'Nguyen', 'Haddad', 'Silva' );
	}

	/**
	 * Booking notes, mostly empty.
	 *
	 * @return array<int, string>
	 */
	public static function notes(): array {
		return array( '', '', '', '', '', '', 'Birthday dinner, could we have a candle on dessert?', 'One guest is coeliac.', 'Terrace if possible.', 'We have a stroller.', 'Anniversary.', 'Nut allergy (severe).', 'High chair needed, please.', 'Window table if available.', 'Vegetarian guest in our party.' );
	}
}
